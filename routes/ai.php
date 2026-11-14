<?php

use App\Http\Middleware\EnsureMcpGrant;
use App\Mcp\Servers\AurumServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

// OAuth discovery and dynamic client registration for AI clients (Claude Code, Codex, OpenClaw …).
// Registration is public by design (RFC 7591), so throttle it.
Route::middleware('throttle:10,1')->group(function (): void {
    Mcp::oauthRoutes();
});

// Passport token of an AI client plus the permissions the user granted it (mcp_grants).
Mcp::web('/mcp/aurum', AurumServer::class)
    ->middleware(['throttle:mcp', 'auth:api', EnsureMcpGrant::class])
    ->name('mcp.aurum');
