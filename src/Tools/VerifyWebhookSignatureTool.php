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
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;
use ZoweSoft\LaravelCredo\Data\WebhookEvent;
use ZoweSoft\LaravelCredo\Exceptions\InvalidSignatureException;
use ZoweSoft\LaravelCredo\Facades\Credo;

/**
 * MCP tool verifying the signature of an inbound Credo webhook payload.
 *
 * Wraps {@see Credo::webhooks()->validate()} from zowesoft/laravel-credo,
 * which recomputes the HMAC-SHA512 signature of the business code with the
 * configured secret key and compares it against the sent signature.
 *
 * Useful when debugging webhooks: paste the `X-Credo-Signature` header and
 * the JSON body Credo posted and the tool reports the verified event type
 * and transaction summary.
 *
 * @see https://docs.credocentral.com Credo developer documentation
 */
#[Name('verify-webhook-signature')]
#[Title('Verify a Credo Webhook Signature')]
#[Description('Verify the signature of a Credo webhook payload. Provide the value of the X-Credo-Signature header and the decoded JSON body Credo sent; the tool checks the HMAC signature against your configured Credo secret key and, if valid, returns the event type and a transaction summary. Read-only: this tool never moves money.')]
#[IsReadOnly]
#[IsIdempotent]
class VerifyWebhookSignatureTool extends Tool
{
    /**
     * Define the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'signature' => $schema->string()
                ->description('The value of the X-Credo-Signature header from the incoming webhook request.')
                ->required(),

            'payload' => $schema->object()
                ->description('The decoded JSON body of the webhook request, exactly as Credo sent it. Do not modify or re-order the data.')
                ->required(),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'signature' => ['required', 'string'],
            'payload' => ['required', 'array'],
        ], [
            'signature.required' => 'You must provide the X-Credo-Signature header value from the webhook request.',
            'payload.required' => 'You must provide the decoded JSON body of the webhook request.',
        ]);

        try {
            $event = Credo::webhooks()->validate(
                $validated['signature'],
                $validated['payload'],
            );
        } catch (InvalidSignatureException) {
            return Response::error(
                'Signature verification failed. The signature does not match the payload for the configured Credo secret key, so this webhook cannot be trusted.'
            );
        } catch (Throwable $e) {
            return Response::error(
                'Could not validate the webhook payload: '.$e->getMessage()
            );
        }

        return Response::structured([
            'valid' => true,
            'event' => $event->event,
            'business_code' => $event->businessCode(),
            'successful' => $event->isSuccessful(),
            'failed' => $event->isFailed(),
            'settlement' => $event->isSettlement(),
            'transfer_reversal' => $event->isTransferReversal(),
            'transaction' => $this->transactionSummary($event),
        ]);
    }

    /**
     * Build a compact transaction summary from the webhook event.
     *
     * @return array<string, mixed>
     */
    protected function transactionSummary(WebhookEvent $event): array
    {
        try {
            $transaction = $event->transaction();
        } catch (Throwable) {
            return [];
        }

        return [
            'successful' => $transaction->successful(),
            'status' => $transaction->status()?->label(),
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'customer_email' => $transaction->email,
            'credo_reference' => $transaction->credoReference,
            'business_reference' => $transaction->reference,
        ];
    }
}
