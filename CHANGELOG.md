# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.0] - 2026-10-07

### Added

- `initialize-transaction` tool: creates a Credo payment and returns the
  checkout link plus both transaction references. **Opt-in and disabled by
  default** — the tool is only registered when `CREDO_MCP_ALLOW_WRITES=true`,
  and is annotated `destructiveHint` for careful AI clients.
- New `allow_writes` config key (`CREDO_MCP_ALLOW_WRITES` env) and expanded
  security documentation in the README.

## [0.1.0] - 2026-10-06

### Added

- `CredoServer` MCP server (Laravel MCP) exposing read-only Credo capabilities.
- `verify-transaction` tool: verifies a payment by transaction reference, with
  optional expected amount/currency checks powered by `Transaction::matches()`.
- `verify-webhook-signature` tool: validates an inbound Credo webhook
  signature against the configured secret key and summarizes the event.
- Auto-registration of the local (stdio) server under the `credo` handle,
  controllable via `CREDO_MCP_ENABLED` and `CREDO_MCP_LOCAL_HANDLE`.
- Publishable config file (`laravel-credo-mcp-config` tag).
- Test suite (Pest) and GitHub Actions workflow for PHP 8.2–8.4 × Laravel 11–12.
