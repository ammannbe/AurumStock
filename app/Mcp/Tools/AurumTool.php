<?php

namespace App\Mcp\Tools;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

abstract class AurumTool extends Tool
{
    /**
     * The authenticated user; every query of a tool is scoped to them.
     *
     * @throws AuthenticationException
     */
    protected function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException('Unauthenticated.');
        }

        return $user;
    }

    /**
     * Short human-readable summary plus the full structured payload.
     *
     * @param  array<string, mixed>  $data
     */
    protected function respond(string $summary, array $data): ResponseFactory
    {
        return Response::make(Response::text($summary))->withStructuredContent($data);
    }

    /**
     * @return array{0: int, 1: int}
     */
    protected function pagination(Request $request, int $default = 20, int $max = 50): array
    {
        $page = max(1, (int) $request->get('page', 1));
        $perPage = min($max, max(1, (int) $request->get('per_page', $default)));

        return [$page, $perPage];
    }
}
