# Connect the Credo MCP server to Claude Desktop

A practical walkthrough for exposing your Laravel app's Credo payment data to Claude Desktop (or Claude Code) using [zowesoft/laravel-credo-mcp](https://github.com/zowesoft/laravel-credo-mcp). No new services, no proxies — Claude launches your app's artisan command and talks to it over stdio.

> **Safety-first summary:** by default the server is 100% read-only (`verify-transaction`, `verify-webhook-signature`). Payment creation is hidden until you explicitly enable the write gate. This guide shows how to enable it *safely* at the end.

## What you need

- A Laravel app (11.45+, 12, or 13) with your Credo keys configured:
  ```env
  CREDO_PUBLIC_KEY=0PUB-xxxx   # or 1PUB-... for live
  CREDO_SECRET_KEY=0PRI-xxxx   # or 1PRI-... for live
  CREDO_MODE=DEMO              # DEMO (sandbox) or LIVE
  ```
- The packages installed in that app:
  ```bash
  composer require zowesoft/laravel-credo
  composer require zowesoft/laravel-credo-mcp
  ```
- Claude Desktop (or Claude Code) installed.

## Step 1 — Verify the server works locally

Before connecting Claude, confirm the MCP server starts and lists its tools:

```bash
php artisan mcp:start credo
```

It will sit silently waiting for JSON-RPC input over stdio — that means it's working. Press `Ctrl+C` to stop.

For an interactive session with a real UI, use Laravel MCP's inspector (requires Node.js):

```bash
php artisan mcp:inspector credo
```

## Step 2 — Register the server in Claude Desktop

Claude Desktop reads MCP servers from `claude_desktop_config.json`:

- **Windows:** `%APPDATA%\Claude\claude_desktop_config.json`
- **macOS:** `~/Library/Application Support/Claude/claude_desktop_config.json`

Open the file and add your server. The command must point at the **absolute path of `artisan`** in your app, and Claude needs the environment variables because MCP servers run outside your normal shell environment:

```json
{
  "mcpServers": {
    "credo": {
      "command": "php",
      "args": [
        "C:\\laragon\\www\\myshop\\artisan",
        "mcp:start",
        "credo"
      ],
      "env": {
        "CREDO_PUBLIC_KEY": "0PUB-xxxx",
        "CREDO_SECRET_KEY": "0PRI-xxxx",
        "CREDO_MODE": "DEMO",
        "APP_ENV": "production"
      }
    }
  }
}
```

macOS/Linux version (note forward slashes):

```json
{
  "mcpServers": {
    "credo": {
      "command": "php",
      "args": [
        "/Users/you/sites/myshop/artisan",
        "mcp:start",
        "credo"
      ],
      "env": {
        "CREDO_PUBLIC_KEY": "0PUB-xxxx",
        "CREDO_SECRET_KEY": "0PRI-xxxx",
        "CREDO_MODE": "DEMO",
        "APP_ENV": "production"
      }
    }
  }
}
```

Tips:

- If `php` isn't on Claude's `PATH` (common on Windows with Laragon), use the absolute PHP binary instead — e.g. `"command": "C:\\laragon\\bin\\php\\php-8.4.18-Win32-vs17-x64\\php.exe"`.
- `APP_ENV=production` keeps the app from accidentally running debug behavior.
- Restart Claude Desktop fully (quit from the tray, not just close the window) after editing the file.

## Step 3 (Claude Code only) — one-liner registration

If you use Claude Code, skip the JSON and register directly:

```bash
claude mcp add credo -- php /path/to/your/app/artisan mcp:start credo
```

## Step 4 — Verify the tools appear

Start a new Claude conversation and click the **tools (🔧/hammer) icon** in the message box. You should see:

| Tool | Kind | What it does |
|---|---|---|
| `verify-transaction` | read-only | Confirms a payment by `transRef` |
| `verify-webhook-signature` | read-only | Validates an inbound webhook |
| `initialize-transaction` | write | **Only present when the write gate is on** |

Now try it:

> *"Verify Credo transaction `VS-ABC123` and tell me if the customer paid ₦1,500."*

Claude will call `verify-transaction(reference: "VS-ABC123", expected_amount: 1500.0, expected_currency: "NGN")` and report the result, including a `matches_expectations` check when you give it expectations.

## Step 5 — Enable the write gate **safely**

`initialize-transaction` creates real payment links with your configured public key. It is **off by default**: unless the server is started with `CREDO_MCP_ALLOW_WRITES=true`, Claude cannot even see the tool.

If you want Claude to be able to create payment links, turn the gate on deliberately:

1. **Start with sandbox.** Keep `CREDO_MODE=DEMO` while testing — write-gated or not, money-wise this waving flag is real only in `LIVE` mode.
2. **Opt in via environment:** add `CREDO_MCP_ALLOW_WRITES: "true"` to the `env` block of the *one* server entry you trust, not globally. The gate is per-environment, so a shared dev machine can stay read-only.
3. **Restart the server** (quit Claude Desktop and reopen — the flag is read at startup).

With the gate on, try:

> *"Create a ₦2,500 payment link for order ORDER-88, email buyer@example.com."*

Claude will call `initialize-transaction(amount: 2500.0, email: "buyer@example.com", reference: "ORDER-88")` and return the `authorization_url` plus both references.

### Guardrails that ship in the box

- The tool is annotated `destructiveHint`, so careful AI clients confirm before writing.
- The server instructions tell the model to prefer read-only tools unless you explicitly ask to create a payment.
- Passing an existing pending `reference` reuses that payment instead of creating a duplicate.
- **The tool can't move money by itself** — it only creates a checkout link. A customer must still complete it. Never fulfill an order until `verify-transaction` confirms the payment server-side.

### A hardening checklist for live mode

- [ ] Run the write-gated server on a machine/user you would trust with your admin panel.
- [ ] Keep `CREDO_MODE=LIVE` keys out of shared configs; use a dedicated key set for AI-triggered payments if Credo supports it.
- [ ] Review each `initialize-transaction` call in your app's logs (the core package logs requests when a channel is configured).
- [ ] Use the `reference` argument every time and reconcile against your orders table.
- [ ] Prefer read-only servers for team/support use; reserve write-gated servers for your own operator account.

## Troubleshooting

**Claude shows no tools.**Fully quit Claude Desktop and reopen; check the config file is valid JSON (no trailing commas). Run `php artisan mcp:start credo` manually — if it errors, fix that first.

**"The handle `credo` is not a registered local MCP server."** The MCP package's provider didn't boot. Confirm `zowesoft/laravel-credo-mcp` is installed and `CREDO_MCP_ENABLED` isn't `false` in the environment Claude passes.

**Server starts but tools fail with connection errors.** The `env` block is missing or incomplete — Claude launches the process without your shell's environment, so every variable the app needs must be listed there.

**`initialize-transaction` is missing entirely.** That's the gate working. Add `CREDO_MCP_ALLOW_WRITES: "true"` to the server's `env` block and restart Claude Desktop.

**Wrong sandbox data (or "Invalid authorization key").** Mismatched `CREDO_MODE`/keys: sandbox keys only work with `DEMO`.

## What you've unlocked

- Payment verification and webhook debugging through natural language, with your keys never leaving your machine
- Optional, deliberately-gated payment creation
- A pattern that still applies as the package gains more tools — the core `zowesoft/laravel-credo` API surface stays the single source of truth

Questions or issues? [Open one on GitHub](https://github.com/zowesoft/laravel-credo-mcp/issues).
