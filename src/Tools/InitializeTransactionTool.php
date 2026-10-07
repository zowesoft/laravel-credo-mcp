<?php

declare(strict_types=1);

namespace ZoweSoft\LaravelCredoMcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Throwable;
use ZoweSoft\LaravelCredo\Enums\Currency;
use ZoweSoft\LaravelCredo\Facades\Credo;

/**
 * MCP tool creating a Credo payment link (write operation).
 *
 * This is the package's first non-read-only tool. It is registered ONLY when
 * `CREDO_MCP_ALLOW_WRITES=true` (or the equivalent config key), so read-only
 * deployments never expose it. Misuse cannot happen silently: the tool is
 * invisible to AI clients unless the operator opted in.
 *
 * The payment is created through {@see Credo::payment()} from
 * zowesoft/laravel-credo, which validates keys, applies the configured
 * callback URL and rides the package's retry/logging support.
 *
 * @see https://docs.credocentral.com/docs/reference/transactions/initializeTransaction Credo initialize transaction API
 */
#[Name('initialize-transaction')]
#[Title('Initialize a Credo Payment')]
#[Description('Create a new Credo payment (checkout link) with the configured public key. Provide the amount in major units (e.g. 1500.00 for ₦1,500), the customer email, the currency ISO code and an optional unique business reference; returns the authorization URL to send the customer to plus both transaction references. This tool CREATES A PAYMENT and is only available on servers with CREDO_MCP_ALLOW_WRITES=true.')]
#[IsDestructive]
#[IsOpenWorld]
class InitializeTransactionTool extends Tool
{
    /**
     * Register this tool only when the operator opted in to write operations.
     */
    public function shouldRegister(): bool
    {
        return (bool) config('laravel-credo-mcp.allow_writes', false);
    }

    /**
     * Define the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'amount' => $schema->number()
                ->description('Amount in major units, e.g. 1500.0 for ₦1,500.00.')
                ->required(),

            'email' => $schema->string()
                ->description('Customer email address, e.g. buyer@example.com.')
                ->required(),

            'currency' => $schema->string()
                ->description('Optional 3-letter ISO currency code, e.g. NGN. Defaults to the configured package currency.'),

            'reference' => $schema->string()
                ->description('Optional unique business reference to correlate the payment with your order, e.g. ORDER-1. Pass an existing pending reference to reuse it instead of creating a duplicate payment.'),

            'callback_url' => $schema->string()
                ->description('Optional HTTPS URL to redirect the customer to after payment. Defaults to the configured package callback URL.'),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        if (! $this->shouldRegister()) {
            return Response::error(
                'Writing payments is disabled on this MCP server. Set CREDO_MCP_ALLOW_WRITES=true and restart the server to enable it.'
            );
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'email' => ['required', 'email'],
            'currency' => ['nullable', 'string', 'size:3', 'in:'.implode(',', array_map(fn (Currency $c) => $c->value, Currency::cases()))], // NGN, USD — see Currency enum
            'reference' => ['nullable', 'string', 'max:100'],
            'callback_url' => ['nullable', 'string', 'url'],
        ], [
            'amount.required' => 'You must provide the payment amount in major units, for example 1500.0 for ₦1,500.00.',
            'email.required' => 'You must provide the customer email address.',
            'currency.in' => 'The currency must be supported by the installed Credo package, currently NGN and USD.',
        ]);

        $builder = Credo::payment()
            ->amountInMajorUnits((float) $validated['amount'])
            ->email($validated['email']);

        if (isset($validated['currency'])) {
            $builder->currency($validated['currency']);
        }

        if (isset($validated['reference']) && trim($validated['reference']) !== '') {
            $builder->reference(trim($validated['reference']));
        }

        if (isset($validated['callback_url']) && trim($validated['callback_url']) !== '') {
            $builder->callbackUrl(trim($validated['callback_url']));
        }

        try {
            $response = $builder->send();
        } catch (Throwable $e) {
            return Response::error(
                'Credo rejected the payment initialization: '.$e->getMessage()
            );
        }

        return Response::structured([
            'authorization_url' => $response->authorizationUrl,
            'credo_reference' => $response->credoReference,
            'business_reference' => $response->reference,
            'crn' => $response->crn,
            'amount' => (float) $validated['amount'],
            'currency' => $validated['currency'] ?? Currency::NGN->value,
            'email' => $validated['email'],
            'next_step' => 'Redirect the customer to the authorization URL. Verify server-side with verify-transaction using the returned credo_reference before delivering value.',
        ]);
    }
}
