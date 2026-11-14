<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Present;
use App\Models\Supplier;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list-suppliers')]
#[Description('List the user\'s suppliers (dealers), optionally filtered by a name search. Use this to find a supplier_id before creating an order.')]
#[IsReadOnly]
class ListSuppliersTool extends AurumTool
{
    public function handle(Request $request): ResponseFactory
    {
        $user = $this->user($request);

        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $search = trim((string) $request->get('search', ''));

        $suppliers = Supplier::query()
            ->where('user_id', $user->id)
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->limit((int) $request->get('limit', 50))
            ->get();

        $items = $suppliers->map(fn (Supplier $supplier): array => Present::supplier($supplier))->all();

        $summary = $suppliers->isEmpty()
            ? 'No suppliers found.'
            : $suppliers->count().' supplier(s): '.$suppliers->map(fn (Supplier $s): string => "#{$s->id} {$s->name}")->implode(', ');

        return $this->respond($summary, ['suppliers' => $items]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Part of the supplier name.'),
            'limit' => $schema->integer()->description('Maximum results (default 50, max 200).'),
        ];
    }
}
