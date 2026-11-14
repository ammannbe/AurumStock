<?php

namespace App\Mcp\Tools;

use App\Mcp\McpPermission;
use App\Mcp\Support\Present;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get-order')]
#[Description('Get a single order of the user including its products and receipt list.')]
#[IsReadOnly]
class GetOrderTool extends AurumTool
{
    protected function permission(): McpPermission
    {
        return McpPermission::Read;
    }

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->user($request);

        $data = $request->validate([
            'order_id' => ['required', 'integer'],
        ]);

        $order = Order::query()->with(['supplier', 'products'])->find($data['order_id']);

        if (! $order || Gate::forUser($user)->denies('view', $order)) {
            throw ValidationException::withMessages(['order_id' => "Order #{$data['order_id']} not found."]);
        }

        /** @var Collection<int, Product> $orderProducts */
        $orderProducts = $order->products;
        $products = $orderProducts->map(fn (Product $product): array => Present::product($product))->all();

        $summary = "Order #{$order->id} {$order->order_number}"
            .($order->supplier ? " from {$order->supplier->name}" : '')
            .($order->order_date ? " on {$order->order_date->toDateString()}" : '')
            .': '.count($products).' product(s), '.count($order->receiptEntries()).' receipt(s).';

        return $this->respond($summary, [
            'order' => [
                ...Present::order($order),
                'receipts' => Present::receipts($order),
                'products' => $products,
            ],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'order_id' => $schema->integer()->required(),
        ];
    }
}
