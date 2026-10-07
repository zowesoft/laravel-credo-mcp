<?php

use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use ZoweSoft\LaravelCredoMcp\Tests\TestCase;
use ZoweSoft\LaravelCredoMcp\Tools\VerifyWebhookSignatureTool;

/**
 * Whether the tool call resolved to an error response.
 */
function webhookToolIsError(Response|ResponseFactory $response): bool
{
    return $response instanceof ResponseFactory
        ? $response->responses()->first()->isError()
        : $response->isError();
}

/**
 * Extract the structured payload from a tool response.
 */
function webhookToolPayload(Response|ResponseFactory $response): array
{
    return $response instanceof ResponseFactory
        ? $response->getStructuredContent()
        : json_decode($response->content()->toJson(), true);
}

function webhookPayload(): array
{
    return [
        'event' => 'transaction.successful',
        'data' => [
            'businessCode' => '700607002190001',
            'transRef' => 'VS-WEBHOOK-REF',
            'businessRef' => 'ORDER-9',
            'status' => 0,
            'transAmount' => 2500.0,
            'debitedAmount' => 2500.0,
            'transFeeAmount' => 37.5,
            'customerId' => 'buyer@example.com',
            'currencyCode' => 'NGN',
        ],
    ];
}

function signedWebhookPayload(): array
{
    $payload = webhookPayload();

    $signature = hash('sha512', TestCase::TEST_SECRET_KEY.$payload['data']['businessCode']);

    return [$payload, $signature];
}

it('verifies a correctly signed webhook payload', function () {
    [$payload, $signature] = signedWebhookPayload();

    $response = (new VerifyWebhookSignatureTool)->handle(
        new Request(['signature' => $signature, 'payload' => $payload]),
    );

    expect(webhookToolIsError($response))->toBeFalse();

    $decoded = webhookToolPayload($response);

    expect($decoded['valid'])->toBeTrue()
        ->and($decoded['event'])->toBe('transaction.successful')
        ->and($decoded['business_code'])->toBe('700607002190001')
        ->and($decoded['transaction']['credo_reference'])->toBe('VS-WEBHOOK-REF');
});

it('returns an error response for a tampered signature', function () {
    [$payload] = signedWebhookPayload();

    $response = (new VerifyWebhookSignatureTool)->handle(
        new Request(['signature' => hash('sha512', 'wrong-secret-700607002190001'), 'payload' => $payload]),
    );

    expect(webhookToolIsError($response))->toBeTrue();
});

it('rejects a call without a signature', function () {
    [$payload] = signedWebhookPayload();

    (new VerifyWebhookSignatureTool)->handle(new Request(['payload' => $payload]));
})->throws(ValidationException::class);
