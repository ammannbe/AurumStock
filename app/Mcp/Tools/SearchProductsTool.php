<?php

namespace App\Mcp\Tools;

use App\Enums\Material;
use App\Enums\ProductType;
use App\Mcp\McpPermission;
use App\Mcp\Support\Present;
use App\Mcp\Support\ProductInput;
use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('search-products')]
#[Description('Search the user\'s own products (holdings) with optional filters. Paginated; returns a compact list. Useful to check whether a purchase was already imported.')]
#[IsReadOnly]
class SearchProductsTool extends AurumTool
{
    protected function permission(): McpPermission
    {
        return McpPermission::Read;
    }

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->user($request);

        $data = validator(ProductInput::normalizeEnums($request->all()), [
            'search' => ['nullable', 'string', 'max:255'],
            'material' => ['nullable', Rule::enum(Material::class)],
            'type' => ['nullable', Rule::enum(ProductType::class)],
            'depository_id' => ['nullable', 'integer'],
            'order_id' => ['nullable', 'integer'],
            'without_order' => ['nullable', 'boolean'],
            'year' => ['nullable', 'integer'],
            'purchase_date_from' => ['nullable', 'date_format:Y-m-d'],
            'purchase_date_to' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ])->validate();

        [$page, $perPage] = $this->pagination($request);
        $search = trim((string) ($data['search'] ?? ''));

        $query = Product::query()
            ->where('user_id', $user->id)
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('reference_number', 'like', '%'.$search.'%')))
            ->when(isset($data['material']), fn (Builder $q) => $q->where('material', $data['material']))
            ->when(isset($data['type']), fn (Builder $q) => $q->where('type', $data['type']))
            ->when(isset($data['depository_id']), fn (Builder $q) => $q->where('depository_id', $data['depository_id']))
            ->when(isset($data['order_id']), fn (Builder $q) => $q->where('order_id', $data['order_id']))
            ->when((bool) ($data['without_order'] ?? false), fn (Builder $q) => $q->whereNull('order_id'))
            ->when(isset($data['year']), fn (Builder $q) => $q->where('year', $data['year']))
            ->when(isset($data['purchase_date_from']), fn (Builder $q) => $q->whereDate('purchase_date', '>=', $data['purchase_date_from']))
            ->when(isset($data['purchase_date_to']), fn (Builder $q) => $q->whereDate('purchase_date', '<=', $data['purchase_date_to']));

        $total = (clone $query)->count();

        $products = $query
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get();

        $items = $products->map(fn (Product $product): array => Present::product($product))->all();
        $lastPage = max(1, (int) ceil($total / $perPage));

        $summary = $total === 0
            ? 'No products found.'
            : "{$total} product(s) found, showing page {$page} of {$lastPage}: "
                .$products->map(fn (Product $p): string => "#{$p->id} {$p->amount}× {$p->name}")->implode('; ');

        return $this->respond($summary, [
            'products' => $items,
            'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'last_page' => $lastPage],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Part of the product name or reference number.'),
            'material' => $schema->string()->enum(['gold', 'silver', 'platinum', 'palladium']),
            'type' => $schema->string()->enum(['ingot', 'coin']),
            'depository_id' => $schema->integer(),
            'order_id' => $schema->integer(),
            'without_order' => $schema->boolean()->description('Only products not linked to any order.'),
            'year' => $schema->integer()->description('Minting year.'),
            'purchase_date_from' => $schema->string()->format('date'),
            'purchase_date_to' => $schema->string()->format('date'),
            'page' => $schema->integer()->description('Page number (default 1).'),
            'per_page' => $schema->integer()->description('Items per page (default 20, max 50).'),
        ];
    }
}
