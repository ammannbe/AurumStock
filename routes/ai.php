<?php

use App\Http\Middleware\AuthenticateMcpToken;
use App\Mcp\Servers\AurumServer;
use Laravel\Mcp\Facades\Mcp;

// Personal MCP token auth (see `php artisan app:mcp-token`); throttled before auth to slow down token guessing.
Mcp::web('/mcp/aurum', AurumServer::class)
    ->middleware(['throttle:mcp', AuthenticateMcpToken::class])
    ->name('mcp.aurum');
