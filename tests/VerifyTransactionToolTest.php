<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use ZoweSoft\LaravelCredoMcp\Tools\VerifyTransactionTool;

/**
 * Whether the tool call resolved to an error response.
 */
function transactionToolIsError(Response|ResponseFactory $response): bool
{
    return $response instanceof ResponseFactory
        ? $response->responses()->first()->isError()
        : $response->isError();
}

/**
 * Extract the structured payload from a tool response.
 */
function transactionToolPayload(Response|ResponseFactory $response): array
{
    return $response instanceof ResponseFactory
        ? $response->getStructuredContent()
        : json_decode($response->content()->toJson(), true);
}

function fakeVerifiedTransaction(string $reference = 'VS-TEST-REF'): void
{
    Http::fake([
        "https://api.credodemo.com/transaction/{$reference}/verify" => Http::response([
            'status' => 200,
            'message' => 'Successful',
            'data' => [
                'transRef' => $reference,
                'businessRef' => 'ORDER-1',
                'status' => 0,
                'transAmount' => 1500.0,
                'debitedAmount' => 1500.0,
                'transFeeAmount' => 22.5,
                'settlementAmount' => 1477.5,
                'customerId' => 'buyer@example.com',
                'currencyCode' => 'NGN',
                'transactionDate' => '2026-10-01 12:00:00',
                'paymentMethod' => 'Card',
                'paymentMethodType' => 'MASTER',
            ],
        ]),
    ]);
}

it('verifies a transaction and returns a structured summary', function () {
    fakeVerifiedTransaction('VS-TEST-REF');

    $response = (new VerifyTransactionTool)->handle(
        new Request(['reference' => 'VS-TEST-REF']),
    );

    expect(transactionToolIsError($response))->toBeFalse();

    $payload = transactionToolPayload($response);

    expect($payload['successful'])->toBeTrue()
        ->and($payload['credo_reference'])->toBe('VS-TEST-REF')
        ->and($payload['business_reference'])->toBe('ORDER-1')
        ->and($payload['amount'])->toBe(1500.0)
        ->and($payload['currency'])->toBe('NGN')
        ->and($payload['customer_email'])->toBe('buyer@example.com')
        ->and($payload['matches_expectations'])->toBeNull();
});

it('checks expected amount and currency with the matches checklist', function () {
    fakeVerifiedTransaction('VS-TEST-REF');

    $payload = transactionToolPayload((new VerifyTransactionTool)->handle(new Request([
        'reference' => 'VS-TEST-REF',
        'expected_amount' => 1500.0,
        'expected_currency' => 'NGN',
    ])));

    expect($payload['matches_expectations'])->toBeTrue();

    $mismatched = transactionToolPayload((new VerifyTransactionTool)->handle(new Request([
        'reference' => 'VS-TEST-REF',
        'expected_amount' => 99.0,
    ])));

    expect($mismatched['matches_expectations'])->toBeFalse();
});

it('returns an error response when Credo rejects the verification', function () {
    Http::fake([
        'https://api.credodemo.com/transaction/VS-BAD-REF/verify' => Http::response(
            ['status' => 401, 'message' => 'Invalid authorization key'],
            401,
        ),
    ]);

    $response = (new VerifyTransactionTool)->handle(
        new Request(['reference' => 'VS-BAD-REF']),
    );

    expect(transactionToolIsError($response))->toBeTrue();
});

it('rejects a call without a reference', function () {
    (new VerifyTransactionTool)->handle(new Request([]));
})->throws(ValidationException::class);
