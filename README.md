# Laravel Credo MCP

[![Latest Version on Packagist](https://img.shields.io/packagist/v/zowesoft/laravel-credo-mcp.svg)](https://packagist.org/packages/zowesoft/laravel-credo-mcp)
[![MIT Licensed](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE.md)
[![run-tests](https://github.com/zowesoft/laravel-credo-mcp/actions/workflows/tests.yml/badge.svg)](https://github.com/zowesoft/laravel-credo-mcp/actions)

An **MCP (Model Context Protocol) server** for the [Credo payment gateway](https://credocentral.com), built on [Laravel MCP](https://laravel.com/docs/mcp) and powered by [zowesoft/laravel-credo](https://github.com/zowesoft/laravel-credo).

Let any AI client — Claude Desktop, Cursor, Claude Code, Laravel Boost — verify Credo transactions and validate webhook signatures through natural language, using your app's configured Credo keys. Read-only by default; payment creation is available behind an explicit opt-in.

## Requirements

- PHP 8.2+
- Laravel 11.45+, 12, or 13
- `zowesoft/laravel-credo` ^1.0 (installed automatically) with your `CREDO_PUBLIC_KEY` / `CREDO_SECRET_KEY` configured

## Installation

```bash
composer require zowesoft/laravel-credo-mcp
```

The package registers a **local (stdio) MCP server** under the handle `credo` automatically. That's it.

## Connecting an AI client

Run the server with Laravel MCP's local server command:

```bash
php artisan mcp:start credo
```

### Claude Desktop / Claude Code

Point your MCP client at the artisan command:

```json
{
  "mcpServers": {
    "credo": {
      "command": "php",
      "args": ["/absolute/path/to/your/app/artisan", "mcp:start", "credo"]
    }
  }
}
```

### Web (HTTP) servers

If you prefer to expose the server over HTTP, register it in your application's `routes/ai.php`:

```php
use Laravel\Mcp\Facades\Mcp;
use ZoweSoft\LaravelCredoMcp\Servers\CredoServer;

Mcp::web('/mcp/credo', CredoServer::class)
    ->middleware(['auth:sanctum']); // protect it — it uses your live keys
```

## Tools

### `verify-transaction`

Verify a payment with Credo by its transaction reference (`transRef`).

| Argument | Type | Required | Description |
|---|---|---|---|
| `reference` | string | yes | The Credo transaction reference to verify |
| `expected_amount` | number | no | Reports whether the paid amount matches |
| `expected_currency` | string | no | 3-letter ISO code (e.g. `NGN`); reports whether it matches |

Returns a structured summary: successful flag, human-readable status, amount, currency, fees, customer email, both references, payment method — and a `matches_expectations` checklist result when expectations are provided.

```
"Was order ORDER-1 actually paid?"
→ verify-transaction(reference: "VS-ABC123", expected_amount: 1500, expected_currency: "NGN")
```

### `verify-webhook-signature`

Validate an inbound Credo webhook against your configured secret key.

| Argument | Type | Required | Description |
|---|---|---|---|
| `signature` | string | yes | The `X-Credo-Signature` header value |
| `payload` | object | yes | The decoded JSON body Credo posted |

Returns `valid: true` with the event type and a transaction summary, or a clear error when the signature does not match. Perfect for debugging webhooks.

```
"Is this Credo webhook payload legitimate?"
→ verify-webhook-signature(signature: "abc123...", payload: {...})
```

### `initialize-transaction` *(opt-in write tool)*

Create a new Credo payment and get its checkout link. **Disabled by default.** The tool is only registered when the server is started with `CREDO_MCP_ALLOW_WRITES=true`, so read-only deployments never expose it to AI clients.

| Argument | Type | Required | Description |
|---|---|---|---|
| `amount` | number | yes | Amount in major units (e.g. `1500.0` for ₦1,500.00) |
| `email` | string | yes | Customer email address |
| `currency` | string | no | ISO code; defaults to NGN |
| `reference` | string | no | Your business reference — reuse a pending one instead of creating a duplicate |
| `callback_url` | string | no | Post-payment redirect; defaults to the package config |

Returns the `authorization_url` to send the customer to, plus both transaction references and a suggested next step (verify server-side). Annotated `destructiveHint` so careful AI clients ask before using it.

```
"Create a ₦1,500 payment link for order ORDER-1"
→ initialize-transaction(amount: 1500.0, email: "buyer@example.com", reference: "ORDER-1")
```

## Configuration

Publish the config file to customize:

```bash
php artisan vendor:publish --tag=laravel-credo-mcp-config
```

```php
return [
    // Set CREDO_MCP_ENABLED=false to skip local server registration
    'enabled' => env('CREDO_MCP_ENABLED', true),

    // The local server handle (default: credo)
    'local_handle' => env('CREDO_MCP_LOCAL_HANDLE', 'credo'),

    // Set CREDO_MCP_ALLOW_WRITES=true to expose the initialize-transaction tool
    'allow_writes' => env('CREDO_MCP_ALLOW_WRITES', false),
];
```

The Credo keys, mode, retry and logging settings all come from [`zowesoft/laravel-credo`](https://github.com/zowesoft/laravel-credo#configuration) — this package never handles keys itself and never sends them to the AI client.

## Security notes

- **Read-only by default.** The only write tool, `initialize-transaction`, is invisible to AI clients unless you set `CREDO_MCP_ALLOW_WRITES=true` — and it cannot move money either; it only creates a checkout link that a customer must complete.
- **Keys stay server-side.** Keys are only used inside your Laravel app; they are never exposed to the model or the MCP client.
- **Protect web servers.** If you register the server over HTTP, put authentication middleware in front of it.

## Testing

```bash
composer install
vendor/bin/pest
```

## Changelog

See [CHANGELOG](CHANGELOG.md) and [GitHub Releases](https://github.com/zowesoft/laravel-credo-mcp/releases).

## Contributing

PRs welcome! Please run `vendor/bin/pint` and `vendor/bin/pest` before submitting.

## License

MIT. See [LICENSE.md](LICENSE.md).
