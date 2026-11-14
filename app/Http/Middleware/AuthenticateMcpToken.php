<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates MCP requests with the user's personal MCP token
 * (Authorization: Bearer aurum_…). Stateless: no session is started.
 */
class AuthenticateMcpToken
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $user = is_string($token) && $token !== '' ? User::findByMcpToken($token) : null;

        if (! $user) {
            return response()->json([
                'error' => 'unauthenticated',
                'message' => 'A valid personal MCP token is required (Authorization: Bearer <token>).',
            ], 401);
        }

        Auth::setUser($user);
        $request->setUserResolver(fn (): User => $user);

        return $next($request);
    }
}
