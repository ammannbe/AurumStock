<?php

namespace App\Mcp\Tools;

use App\Mcp\McpPermission;
use App\Mcp\Support\Present;
use App\Mcp\Support\ProductInput;
use App\Mcp\Support\SupplierResolver;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Throwable;

#[Name('create-order')]
#[Description('Create an order (purchase) with optional product line items in one atomic step. The supplier is given by supplier_id or supplier_name (found or created). Each item creates a product linked to the order; use template_id from search-product-templates where possible. Rejects an order_number the user already has. Use dry_run=true first to preview without saving.')]
class CreateOrderTool extends AurumTool
{
    protected function permission(): McpPermission
    {
        return McpPermission::Create;
    }

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->user($request);

        $input = $request->all();

        if (is_array($input['items'] ?? null)) {
            $input['items'] = array_map(
                fn (mixed $item): mixed => is_array($item) ? ProductInput::normalize($item, $user) : $item,
                $input['items'],
            );
        }

        $data = validator($input, [
            'order_number' => ['required', 'string', 'max:255'],
            'order_date' => ['nullable', 'date_format:Y-m-d'],
            'supplier_id' => ['nullable', 'integer', 'prohibits:supplier_name', Rule::exists('suppliers', 'id')->where('user_id', $user->id)],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'dry_run' => ['nullable', 'boolean'],
            'items' => ['nullable', 'array', 'list', 'max:100'],
            'items.*' => ['array'],
            ...ProductInput::rules($user, 'items.*.'),
        ])->validate();

        $orderNumber = trim($data['order_number']);
        $dryRun = (bool) ($data['dry_run'] ?? false);

        $this->ensureOrderNumberIsNew($user->id, $orderNumber);

        DB::beginTransaction();

        try {
            [$supplier, $supplierCreated] = $this->resolveSupplier($user, $data);

            $order = Order::query()->create([
                'user_id' => $user->id,
                'supplier_id' => $supplier?->id,
                'order_number' => $orderNumber,
                'order_date' => $data['order_date'] ?? null,
            ]);

            $products = [];

            foreach ($data['items'] ?? [] as $item) {
                $products[] = Product::query()->create(
                    ProductInput::attributes($item, $user, $order->id, $data['order_date'] ?? null),
                );
            }

            $order->setRelation('supplier', $supplier);

            $dryRun ? DB::rollBack() : DB::commit();
        } catch (UniqueConstraintViolationException) {
            DB::rollBack();

            throw ValidationException::withMessages([
                'order_number' => "You already have an order with number {$orderNumber}.",
            ]);
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        $orderData = Present::order($order);
        $productData = array_map(fn (Product $product): array => Present::product($product), $products);

        if ($dryRun) {
            $orderData['id'] = null;
            $productData = array_map(fn (array $product): array => [...$product, 'id' => null, 'order_id' => null], $productData);
        }

        $summary = ($dryRun ? 'DRY RUN – nothing was saved. Would create' : 'Created')
            ." order {$orderNumber}".($dryRun ? '' : " (#{$order->id})")
            .($supplier ? ' from '.$supplier->name.($supplierCreated ? ' (new supplier)' : '') : '')
            .' with '.count($products).' product(s)'
            .($products === [] ? '.' : ': '.implode('; ', array_map(fn (Product $p): string => "{$p->amount}× {$p->name}", $products)).'.');

        return $this->respond($summary, [
            'dry_run' => $dryRun,
            'order' => $orderData,
            'supplier' => $supplier ? [
                ...Present::supplier($supplier),
                'id' => $dryRun && $supplierCreated ? null : $supplier->id,
                'created' => $supplierCreated,
            ] : null,
            'products' => $productData,
        ]);
    }

    protected function ensureOrderNumberIsNew(int $userId, string $orderNumber): void
    {
        $existing = Order::query()
            ->with('supplier')
            ->where('user_id', $userId)
            ->where('order_number', $orderNumber)
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'order_number' => "You already have an order with number {$orderNumber} (order #{$existing->id}"
                    .($existing->supplier ? ", supplier {$existing->supplier->name}" : '')
                    .'). Use get-order to inspect it, create-product with order_id to add products or attach-order-receipt to add receipts.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: Supplier|null, 1: bool}
     */
    protected function resolveSupplier(User $user, array $data): array
    {
        if (filled($data['supplier_id'] ?? null)) {
            return [Supplier::query()->where('user_id', $user->id)->findOrFail($data['supplier_id']), false];
        }

        if (filled($data['supplier_name'] ?? null)) {
            return SupplierResolver::findOrCreate($user, $data['supplier_name']);
        }

        return [null, false];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'order_number' => $schema->string()->description('Order / invoice number from the receipt.')->required(),
            'order_date' => $schema->string()->format('date')->description('YYYY-MM-DD. Used as default purchase_date of the items.'),
            'supplier_id' => $schema->integer()->description('Existing supplier ID (see list-suppliers).'),
            'supplier_name' => $schema->string()->description('Supplier name; found case-insensitively or created. Use instead of supplier_id.'),
            'items' => $schema->array()->items($schema->object(ProductInput::schema($schema)))->description('Products bought with this order (one entry per line item).'),
            'dry_run' => $schema->boolean()->description('Validate and preview only, nothing is saved.'),
        ];
    }
}
