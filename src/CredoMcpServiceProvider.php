<?php

declare(strict_types=1);

namespace ZoweSoft\LaravelCredoMcp;

use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;
use ZoweSoft\LaravelCredoMcp\Servers\CredoServer;

/**
 * Package service provider.
 *
 * Registers the {@see CredoServer} as a local (stdio) MCP server so AI
 * clients such as Claude Desktop, Cursor or Laravel Boost can talk to the
 * Credo payment gateway through the `zowesoft/laravel-credo` package.
 *
 * Local registration can be turned off with `CREDO_MCP_ENABLED=false` or by
 * publishing the config file. Web (HTTP) registration is left to the
 * consuming application's `routes/ai.php` file — see the README.
 *
 * @see https://docs.credocentral.com Credo developer documentation
 * @see https://laravel.com/docs/mcp Laravel MCP documentation
 */
class CredoMcpServiceProvider extends ServiceProvider
{
    /**
     * Register package configuration.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-credo-mcp.php', 'laravel-credo-mcp');
    }

    /**
     * Bootstrap package services.
     */
    public function boot(): void
    {
        $this->registerPublishing();

        if (! config('laravel-credo-mcp.enabled', true)) {
            return;
        }

        Mcp::local((string) config('laravel-credo-mcp.local_handle', 'credo'), CredoServer::class);
    }

    /**
     * Make the config file publishable.
     */
    protected function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/laravel-credo-mcp.php' => config_path('laravel-credo-mcp.php'),
        ], 'laravel-credo-mcp-config');
    }
}
