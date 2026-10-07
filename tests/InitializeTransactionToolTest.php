<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use ZoweSoft\LaravelCredoMcp\Tools\InitializeTransactionTool;

/**
 * Whether the tool call resolved to an error response.
 */
function initializeToolIsError(Response|ResponseFactory $response): bool
{
    return $response instanceof ResponseFactory
        ? $response->responses()->first()->isError()
        : $response->isError();
}

/**
 * Extract the structured payload from a tool response.
 */
function initializeToolPayload(Response|ResponseFactory $response): array
{
    return $response instanceof ResponseFactory
        ? $response->getStructuredContent()
        : json_decode($response->content()->toJson(), true);
}

it('is not registered when writes are not allowed', function () {
    config(['laravel-credo-mcp.allow_writes' => false]);

    expect((new InitializeTransactionTool)->shouldRegister())->toBeFalse();
});

it('is registered when writes are allowed', function () {
    config(['laravel-credo-mcp.allow_writes' => true]);

    expect((new InitializeTransactionTool)->shouldRegister())->toBeTrue();
});

it('refuses to initialize a payment when writes are disabled', function () {
    config(['laravel-credo-mcp.allow_writes' => false]);

    $response = (new InitializeTransactionTool)->handle(new Request([
        'amount' => 1500.0,
        'email' => 'buyer@example.com',
    ]));

    expect(initializeToolIsError($response))->toBeTrue();

    Http::assertNothingSent();
});

it('initializes a payment and returns the checkout details', function () {
    config(['laravel-credo-mcp.allow_writes' => true]);

    Http::fake([
        'https://api.credodemo.com/transaction/initialize' => Http::response([
            'status' => 200,
            'message' => 'Successfully processed',
            'data' => [
                'authorizationUrl' => 'https://pay.credodemo.com/Abc123',
                'reference' => 'ORDER-77',
                'credoReference' => 'VS-CREDO-REF',
                'Crn' => '0000298483',
            ],
        ]),
    ]);

    $response = (new InitializeTransactionTool)->handle(new Request([
        'amount' => 1500.0,
        'email' => 'buyer@example.com',
        'currency' => 'NGN',
        'reference' => 'ORDER-77',
    ]));

    expect(initializeToolIsError($response))->toBeFalse();

    $payload = initializeToolPayload($response);

    expect($payload['authorization_url'])->toBe('https://pay.credodemo.com/Abc123')
        ->and($payload['credo_reference'])->toBe('VS-CREDO-REF')
        ->and($payload['business_reference'])->toBe('ORDER-77')
        ->and($payload['amount'])->toBe(1500.0)
        ->and($payload['email'])->toBe('buyer@example.com')
        ->and($payload['next_step'])->toContain('verify-transaction');

    Http::assertSent(function ($request) {
        return $request['amount'] === 150000 && $request['email'] === 'buyer@example.com'
            && $request['reference'] === 'ORDER-77';
    });
});

it('validates the currency and rejects unsupported codes', function () {
    config(['laravel-credo-mcp.allow_writes' => true]);

    (new InitializeTransactionTool)->handle(new Request([
        'amount' => 10.0,
        'email' => 'buyer@example.com',
        'currency' => 'XXX',
    ]));
})->throws(ValidationException::class);

it('rejects an invalid email', function () {
    config(['laravel-credo-mcp.allow_writes' => true]);

    (new InitializeTransactionTool)->handle(new Request([
        'amount' => 10.0,
        'email' => 'not-an-email',
    ]));
})->throws(ValidationException::class);

it('returns an error response when Credo rejects the initialization', function () {
    config(['laravel-credo-mcp.allow_writes' => true]);

    Http::fake([
        'https://api.credodemo.com/transaction/initialize' => Http::response([
            'status' => 401,
            'message' => 'Invalid authorization key',
        ], 401),
    ]);

    $response = (new InitializeTransactionTool)->handle(new Request([
        'amount' => 1500.0,
        'email' => 'buyer@example.com',
    ]));

    expect(initializeToolIsError($response))->toBeTrue();
});
