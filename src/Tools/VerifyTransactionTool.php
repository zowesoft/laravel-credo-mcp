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
use ZoweSoft\LaravelCredo\Data\Transaction;
use ZoweSoft\LaravelCredo\Exceptions\CredoException;
use ZoweSoft\LaravelCredo\Facades\Credo;

/**
 * MCP tool verifying a Credo transaction by its reference.
 *
 * Wraps {@see Credo::verify()} from zowesoft/laravel-credo. Optionally
 * checks the paid amount and currency against expectations using
 * {@see Transaction::matches()}.
 *
 * @see https://docs.credocentral.com/docs/developers/verify-transaction Credo verify transaction API
 */
#[Name('verify-transaction')]
#[Title('Verify a Credo Transaction')]
#[Description('Verify a payment with the Credo payment gateway by its transaction reference (transRef). Returns the paid amount, currency, status, customer email and whether the payment was successful. Optionally checks the paid amount and currency against expected values. Read-only: this tool never moves money.')]
#[IsReadOnly]
#[IsIdempotent]
class VerifyTransactionTool extends Tool
{
    /**
     * Define the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'reference' => $schema->string()
                ->description('The Credo transaction reference (transRef) of the payment to verify.')
                ->required(),

            'expected_amount' => $schema->number()
                ->description('Optional expected amount of the payment. When given, the result reports whether the paid amount matches.'),

            'expected_currency' => $schema->string()
                ->description('Optional expected currency ISO code (for example "NGN"). When given, the result reports whether the paid currency matches.'),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'reference' => ['required', 'string'],
            'expected_amount' => ['nullable', 'numeric'],
            'expected_currency' => ['nullable', 'string', 'size:3'],
        ], [
            'reference.required' => 'You must provide the Credo transaction reference (transRef) to verify. Example reference: "VS-ABC123".',
            'expected_currency.size' => 'The expected currency must be a 3-letter ISO code, for example "NGN".',
        ]);

        try {
            $transaction = Credo::verify($validated['reference']);
        } catch (CredoException|Throwable $e) {
            return Response::error(
                'Could not verify the transaction with Credo: '.$e->getMessage()
            );
        }

        $checksExpectations = isset($validated['expected_amount']) || isset($validated['expected_currency']);

        return Response::structured([
            'successful' => $transaction->successful(),
            'status' => $transaction->status()?->label(),
            'status_code' => $transaction->statusCode,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'debited_amount' => $transaction->debitedAmount,
            'fee_amount' => $transaction->feeAmount,
            'settlement_amount' => $transaction->settlementAmount,
            'customer_email' => $transaction->email,
            'credo_reference' => $transaction->credoReference,
            'business_reference' => $transaction->reference,
            'paid_at' => $transaction->transactionDate,
            'payment_method' => $transaction->paymentMethod,
            'payment_method_type' => $transaction->paymentMethodType,
            'matches_expectations' => $checksExpectations
                ? $transaction->matches(
                    isset($validated['expected_amount']) ? (float) $validated['expected_amount'] : null,
                    $validated['expected_currency'] ?? null,
                    null,
                )
                : null,
        ]);
    }
}
