<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Present;
use App\Mcp\Support\ProductInput;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Throwable;

#[Name('create-product')]
#[Description('Create a single product (holding), optionally linked to an existing order of the user (order_id). Use template_id from search-product-templates where possible. Use dry_run=true to preview without saving.')]
class CreateProductTool extends AurumTool
{
    public function handle(Request $request): ResponseFactory
    {
        $user = $this->user($request);

        $data = validator(ProductInput::normalize($request->all(), $user), [
            ...ProductInput::rules($user),
            'order_id' => ['nullable', 'integer'],
            'dry_run' => ['nullable', 'boolean'],
        ])->validate();

        $order = null;

        if (filled($data['order_id'] ?? null)) {
            $order = Order::query()->find($data['order_id']);

            if (! $order || Gate::forUser($user)->denies('update', $order)) {
                throw ValidationException::withMessages(['order_id' => "Order #{$data['order_id']} not found."]);
            }
        }

        $dryRun = (bool) ($data['dry_run'] ?? false);

        DB::beginTransaction();

        try {
            $product = Product::query()->create(
                ProductInput::attributes($data, $user, $order?->id, $order?->order_date?->toDateString()),
            );

            $dryRun ? DB::rollBack() : DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        $productData = Present::product($product);

        if ($dryRun) {
            $productData['id'] = null;
        }

        $summary = ($dryRun ? 'DRY RUN – nothing was saved. Would create' : "Created product #{$product->id}:")
            ." {$product->amount}× {$product->name}"
            .($order ? " in order {$order->order_number}" : '').'.';

        return $this->respond($summary, [
            'dry_run' => $dryRun,
            'product' => $productData,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            ...ProductInput::schema($schema),
            'order_id' => $schema->integer()->description('Optional existing order ID of the user. purchase_date defaults to its order date.'),
            'dry_run' => $schema->boolean()->description('Validate and preview only, nothing is saved.'),
        ];
    }
}
