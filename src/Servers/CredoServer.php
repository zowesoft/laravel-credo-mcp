<?php

declare(strict_types=1);

namespace ZoweSoft\LaravelCredoMcp\Servers;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;
use ZoweSoft\LaravelCredoMcp\Tools\InitializeTransactionTool;
use ZoweSoft\LaravelCredoMcp\Tools\VerifyTransactionTool;
use ZoweSoft\LaravelCredoMcp\Tools\VerifyWebhookSignatureTool;

/**
 * MCP server exposing read-only Credo payment gateway capabilities.
 *
 * Every tool is read-only and safe to connect to any AI client, except the
 * write tool `initialize-transaction`, which is only registered when the
 * operator sets `CREDO_MCP_ALLOW_WRITES=true`.
 *
 * @see https://docs.credocentral.com Credo developer documentation
 * @see https://laravel.com/docs/mcp Laravel MCP documentation
 */
#[Name('Credo MCP Server')]
#[Version('0.2.0')]
#[Instructions('Access to the Credo payment gateway. Use verify-transaction to confirm payment status by transaction reference, and verify-webhook-signature to validate incoming Credo webhook payloads. The initialize-transaction tool creates real payments and is only available when CREDO_MCP_ALLOW_WRITES=true; prefer read-only tools unless the user explicitly asks to create a payment.')]
class CredoServer extends Server
{
    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        VerifyTransactionTool::class,
        VerifyWebhookSignatureTool::class,
        InitializeTransactionTool::class,
    ];

    /**
     * The resources registered with this MCP server.
     *
     * @var array<int, class-string<Server\Resource>>
     */
    protected array $resources = [];

    /**
     * The prompts registered with this MCP server.
     *
     * @var array<int, class-string<Prompt>>
     */
    protected array $prompts = [];
}
