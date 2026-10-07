<?php

declare(strict_types=1);

namespace ZoweSoft\LaravelCredoMcp\Servers;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;
use ZoweSoft\LaravelCredoMcp\Tools\VerifyTransactionTool;
use ZoweSoft\LaravelCredoMcp\Tools\VerifyWebhookSignatureTool;

/**
 * MCP server exposing read-only Credo payment gateway capabilities.
 *
 * Every tool in this server is read-only and safe to connect to any AI
 * client. Write operations (initializing payments) are intentionally not
 * exposed yet; they may arrive in a future release behind an explicit
 * opt-in flag.
 *
 * @see https://docs.credocentral.com Credo developer documentation
 * @see https://laravel.com/docs/mcp Laravel MCP documentation
 */
#[Name('Credo MCP Server')]
#[Version('0.1.0')]
#[Instructions('Read-only access to the Credo payment gateway. Use verify-transaction to confirm payment status by transaction reference, and verify-webhook-signature to validate incoming Credo webhook payloads. This server never moves money.')]
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
