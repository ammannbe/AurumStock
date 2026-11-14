<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Present;
use App\Models\Order;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list-orders')]
#[Description('List the user\'s orders (purchases), newest first, optionally filtered by order number / supplier search, supplier_id or date range. Paginated.')]
#[IsReadOnly]
class ListOrdersTool extends AurumTool
{
    public function handle(Request $request): ResponseFactory
    {
        $user = $this->user($request);

        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'supplier_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        [$page, $perPage] = $this->pagination($request);
        $search = trim((string) ($data['search'] ?? ''));

        $query = Order::query()
            ->where('user_id', $user->id)
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('order_number', 'like', '%'.$search.'%')
                ->orWhereHas('supplier', fn (Builder $s) => $s->where('name', 'like', '%'.$search.'%'))))
            ->when(isset($data['supplier_id']), fn (Builder $q) => $q->where('supplier_id', $data['supplier_id']))
            ->when(isset($data['date_from']), fn (Builder $q) => $q->whereDate('order_date', '>=', $data['date_from']))
            ->when(isset($data['date_to']), fn (Builder $q) => $q->whereDate('order_date', '<=', $data['date_to']));

        $total = (clone $query)->count();

        $orders = $query
            ->with('supplier')
            ->withCount('products')
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get();

        $items = $orders->map(fn (Order $order): array => [
            ...Present::order($order),
            'products_count' => $order->getAttribute('products_count'),
        ])->all();
        $lastPage = max(1, (int) ceil($total / $perPage));

        $summary = $total === 0
            ? 'No orders found.'
            : "{$total} order(s) found, showing page {$page} of {$lastPage}: "
                .$orders->map(fn (Order $o): string => "#{$o->id} {$o->order_number}".($o->supplier ? " ({$o->supplier->name})" : '').($o->order_date ? ' '.$o->order_date->toDateString() : ''))->implode('; ');

        return $this->respond($summary, [
            'orders' => $items,
            'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'last_page' => $lastPage],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Part of the order number or supplier name.'),
            'supplier_id' => $schema->integer(),
            'date_from' => $schema->string()->format('date'),
            'date_to' => $schema->string()->format('date'),
            'page' => $schema->integer()->description('Page number (default 1).'),
            'per_page' => $schema->integer()->description('Items per page (default 20, max 50).'),
        ];
    }
}
