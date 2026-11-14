<?php

namespace App\Mcp\Tools;

use App\Mcp\McpPermission;
use App\Mcp\Support\MediaInput;
use App\Models\McpGrant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

abstract class AurumTool extends Tool
{
    /**
     * Permission the user must have given the AI client for this tool (McpGrant).
     */
    abstract protected function permission(): McpPermission;

    /**
     * A tool is only listed and callable when the user gave the AI client its permission.
     */
    public function shouldRegister(Request $request): bool
    {
        $user = $request->user();

        return $user instanceof User && (McpGrant::current($user)?->allows($this->permission()) ?? false);
    }

    /**
     * The authenticated user; every query of a tool is scoped to them.
     *
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    protected function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException('Unauthenticated.');
        }

        if (! $this->shouldRegister($request)) {
            throw new AuthorizationException('This AI client has no permission for this tool.');
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
     * Decodes a base64 file upload; data URLs ("data:image/png;base64,....") are accepted as well.
     *
     * @throws ValidationException
     */
    protected function decodeBase64(string $content): string
    {
        return MediaInput::decodeBase64($content);
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
