<?php

namespace App\Mcp\Tools;

use App\Mcp\McpPermission;
use App\Mcp\Support\Present;
use App\Mcp\Support\SupplierResolver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('create-supplier')]
#[Description('Find a supplier by exact name or create it if it does not exist yet (find-or-create). Returns the supplier and whether it was newly created.')]
#[IsIdempotent]
class CreateSupplierTool extends AurumTool
{
    protected function permission(): McpPermission
    {
        return McpPermission::Create;
    }

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->user($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        [$supplier, $created] = SupplierResolver::findOrCreate($user, $data['name'], [
            'website' => $data['website'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $summary = $created
            ? "Created supplier #{$supplier->id} {$supplier->name}."
            : "Supplier #{$supplier->id} {$supplier->name} already exists.";

        return $this->respond($summary, [
            'created' => $created,
            'supplier' => Present::supplier($supplier),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Supplier name, e.g. "Degussa".')->required(),
            'website' => $schema->string()->description('Optional website URL (only used when creating).'),
            'notes' => $schema->string()->description('Optional notes (only used when creating).'),
        ];
    }
}
