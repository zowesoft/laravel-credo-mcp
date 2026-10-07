<?php

use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Registrar;
use ZoweSoft\LaravelCredoMcp\CredoMcpServiceProvider;
use ZoweSoft\LaravelCredoMcp\Servers\CredoServer;
use ZoweSoft\LaravelCredoMcp\Tools\InitializeTransactionTool;
use ZoweSoft\LaravelCredoMcp\Tools\VerifyTransactionTool;
use ZoweSoft\LaravelCredoMcp\Tools\VerifyWebhookSignatureTool;

it('registers the local credo server', function () {
    expect(Mcp::getLocalServer('credo'))->not->toBeNull()
        ->and(Mcp::servers())->toHaveKey('credo');
});

it('does not register the local server when disabled', function () {
    config(['laravel-credo-mcp.enabled' => false]);

    $registrar = new Registrar;
    $this->app->instance(Registrar::class, $registrar);

    (new CredoMcpServiceProvider($this->app))->boot();

    expect($registrar->servers())->not->toHaveKey('credo');
});

it('exposes both read-only tools with annotations', function () {
    $tools = (new ReflectionClass(CredoServer::class))->getDefaultProperties()['tools'];

    expect($tools)->toContain(VerifyTransactionTool::class, VerifyWebhookSignatureTool::class);

    foreach ([VerifyTransactionTool::class, VerifyWebhookSignatureTool::class] as $toolClass) {
        $tool = new $toolClass;

        expect($tool->annotations())->toHaveKey('readOnlyHint', true)
            ->and($tool->toArray()['name'])->not->toBeEmpty()
            ->and($tool->toArray()['description'])->not->toBeEmpty();
    }
});

it('registers the write tool only after opting in', function () {
    config(['laravel-credo-mcp.allow_writes' => true]);

    $tool = new InitializeTransactionTool;

    expect((new ReflectionClass(CredoServer::class))->getDefaultProperties()['tools'])->toContain(InitializeTransactionTool::class)
        ->and($tool->shouldRegister())->toBeTrue()
        ->and($tool->annotations())->toHaveKey('destructiveHint', true)
        ->and($tool->annotations())->not->toHaveKey('readOnlyHint');
});
