<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Enable the local MCP server
    |--------------------------------------------------------------------------
    |
    | When enabled, the package registers a local (stdio) MCP server under
    | the handle below so AI clients can launch it with
    | `php artisan mcp:start credo`.
    |
    */

    'enabled' => env('CREDO_MCP_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Local server handle
    |--------------------------------------------------------------------------
    |
    | The name AI clients use to start the local server, e.g.
    | `php artisan mcp:start credo`.
    |
    */

    'local_handle' => env('CREDO_MCP_LOCAL_HANDLE', 'credo'),

];
