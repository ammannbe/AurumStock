<?php

use App\Enums\Material;
use App\Enums\ProductType;
use App\Enums\WeightUnit;
use App\Mcp\McpPermission;
use App\Mcp\Servers\AurumServer;
use App\Mcp\Tools\AttachOrderReceiptTool;
use App\Mcp\Tools\CreateOrderTool;
use App\Mcp\Tools\CreateProductTool;
use App\Mcp\Tools\CreateSupplierTool;
use App\Mcp\Tools\GetOrderTool;
use App\Mcp\Tools\ListDepositoriesTool;
use App\Mcp\Tools\ListOrdersTool;
use App\Mcp\Tools\ListSuppliersTool;
use App\Mcp\Tools\SearchProductsTool;
use App\Mcp\Tools\SearchProductTemplatesTool;
use App\Mcp\Tools\SetProductImageTool;
use App\Models\Depository;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductTemplate;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->depository = Depository::factory()->create(['user_id' => $this->user->id, 'name' => 'Tresor']);

    $this->other = User::factory()->create();
    $this->otherDepository = Depository::factory()->create(['user_id' => $this->other->id]);

    mcpConnect($this->user);
});

function pngBytes(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
}

it('does not let guests use tools', function () {
    app('auth')->forgetGuards();

    AurumServer::tool(ListOrdersTool::class)->assertHasErrors();
});

it('only offers and runs the tools the client was given permission for', function () {
    $order = Order::factory()->create(['user_id' => $this->user->id]);
    mcpConnect($this->user, [McpPermission::Read]);

    AurumServer::tool(ListOrdersTool::class)->assertOk();
    AurumServer::tool(CreateOrderTool::class, ['order_number' => 'X-9'])->assertHasErrors();
    AurumServer::tool(AttachOrderReceiptTool::class, [
        'order_id' => $order->id, 'filename' => 'a.png', 'content_base64' => base64_encode(pngBytes()),
    ])->assertHasErrors();

    expect(Order::query()->where('order_number', 'X-9')->exists())->toBeFalse()
        ->and($order->refresh()->receipts)->toBeEmpty();
});

it('lists and searches only the user\'s suppliers', function () {
    Supplier::factory()->create(['user_id' => $this->user->id, 'name' => 'Degussa']);
    Supplier::factory()->create(['user_id' => $this->user->id, 'name' => 'Philoro']);
    Supplier::factory()->create(['user_id' => $this->other->id, 'name' => 'Degussa Fremd']);

    AurumServer::tool(ListSuppliersTool::class, ['search' => 'degu'])
        ->assertOk()
        ->assertStructuredContentCount(1, 'suppliers')
        ->assertStructuredContentPath('suppliers.0.name', 'Degussa')
        ->assertDontSee('Fremd');
});

it('finds or creates a supplier', function () {
    $existing = Supplier::factory()->create(['user_id' => $this->user->id, 'name' => 'Degussa']);

    AurumServer::tool(CreateSupplierTool::class, ['name' => 'degussa'])
        ->assertOk()
        ->assertStructuredContentPath('created', false)
        ->assertStructuredContentPath('supplier.id', $existing->id);

    AurumServer::tool(CreateSupplierTool::class, ['name' => 'Philoro', 'website' => 'https://philoro.ch'])
        ->assertOk()
        ->assertStructuredContentPath('created', true);

    expect(Supplier::query()->where('user_id', $this->user->id)->where('name', 'Philoro')->exists())->toBeTrue();
});

it('lists only the user\'s depositories', function () {
    AurumServer::tool(ListDepositoriesTool::class)
        ->assertOk()
        ->assertStructuredContentCount(1, 'depositories')
        ->assertStructuredContentPath('depositories.0.id', $this->depository->id);
});

it('searches the global product templates', function () {
    $vreneli = ProductTemplate::factory()->create(['name' => 'Vreneli', 'material' => Material::Gold->value, 'type' => ProductType::Coin->value, 'weight' => 6.4516, 'year' => 1935]);
    ProductTemplate::factory()->create(['name' => 'Maple Leaf', 'material' => Material::Silver->value]);

    AurumServer::tool(SearchProductTemplatesTool::class, ['search' => 'vreneli', 'material' => 'gold', 'weight' => 6.4516])
        ->assertOk()
        ->assertStructuredContentCount(1, 'templates')
        ->assertStructuredContentPath('templates.0.id', $vreneli->id)
        ->assertStructuredContentPath('templates.0.material', 'gold');
});

it('searches only the user\'s products with pagination', function () {
    Product::factory()->count(3)->create(['user_id' => $this->user->id, 'depository_id' => $this->depository->id, 'name' => 'Krugerrand']);
    Product::factory()->create(['user_id' => $this->user->id, 'depository_id' => $this->depository->id, 'name' => 'Barren', 'material' => Material::Silver->value]);
    Product::factory()->create(['user_id' => $this->other->id, 'depository_id' => $this->otherDepository->id, 'name' => 'Krugerrand']);

    AurumServer::tool(SearchProductsTool::class, ['search' => 'kruger', 'per_page' => 2])
        ->assertOk()
        ->assertStructuredContentCount(2, 'products')
        ->assertStructuredContentPath('pagination.total', 3)
        ->assertStructuredContentPath('pagination.last_page', 2);

    AurumServer::tool(SearchProductsTool::class, ['material' => 'silver'])
        ->assertStructuredContentPath('pagination.total', 1);
});

it('lists and gets only the user\'s orders', function () {
    $order = Order::factory()->create(['user_id' => $this->user->id, 'order_number' => 'A-100']);
    Product::factory()->create(['user_id' => $this->user->id, 'depository_id' => $this->depository->id, 'order_id' => $order->id, 'name' => 'Vreneli']);
    $foreign = Order::factory()->create(['user_id' => $this->other->id, 'order_number' => 'B-200']);

    AurumServer::tool(ListOrdersTool::class)
        ->assertOk()
        ->assertStructuredContentCount(1, 'orders')
        ->assertStructuredContentPath('orders.0.order_number', 'A-100')
        ->assertStructuredContentPath('orders.0.products_count', 1);

    AurumServer::tool(GetOrderTool::class, ['order_id' => $order->id])
        ->assertOk()
        ->assertStructuredContentPath('order.order_number', 'A-100')
        ->assertStructuredContentPath('order.products.0.name', 'Vreneli');

    AurumServer::tool(GetOrderTool::class, ['order_id' => $foreign->id])
        ->assertHasErrors(["Order #{$foreign->id} not found."]);
});

it('creates an order with a new supplier and items from a template and explicit fields', function () {
    $template = ProductTemplate::factory()->create([
        'name' => 'Vreneli', 'material' => Material::Gold->value, 'type' => ProductType::Coin->value,
        'weight' => 6.4516, 'weight_unit' => WeightUnit::Gram->value, 'fineness' => '900', 'year' => 1935,
    ]);

    AurumServer::tool(CreateOrderTool::class, [
        'order_number' => '25822',
        'order_date' => '2026-11-01',
        'supplier_name' => 'Degussa',
        'items' => [
            ['template_id' => $template->id, 'amount' => 5, 'price' => 2500, 'price_currency' => 'CHF'],
            ['name' => 'Goldbarren 10 g', 'material' => 'gold', 'type' => 'ingot', 'weight' => 10, 'weight_unit' => 'gram', 'fineness' => '999.9', 'price' => 1100, 'purchase_date' => '2026-11-02'],
        ],
    ])
        ->assertOk()
        ->assertSee('Created order 25822')
        ->assertStructuredContentPath('dry_run', false)
        ->assertStructuredContentPath('supplier.created', true)
        ->assertStructuredContentCount(2, 'products');

    $order = Order::query()->where('user_id', $this->user->id)->where('order_number', '25822')->sole();
    expect($order->supplier?->name)->toBe('Degussa')
        ->and($order->products)->toHaveCount(2);

    /** @var Product $fromTemplate */
    $fromTemplate = $order->products->firstWhere('template_id', $template->id);
    expect($fromTemplate->name)->toBe('Vreneli')
        ->and($fromTemplate->fineness)->toBe('900')
        ->and($fromTemplate->amount)->toBe(5)
        ->and($fromTemplate->depository_id)->toBe($this->depository->id)
        ->and($fromTemplate->purchase_date->toDateString())->toBe('2026-11-01')
        ->and($fromTemplate->source_hash)->toBe($template->source_hash)
        ->and($fromTemplate->isSynchronizedWithTemplate())->toBeTrue();

    /** @var Product $explicit */
    $explicit = $order->products->firstWhere('template_id', null);
    expect($explicit->type)->toBe(ProductType::Ingot)
        ->and($explicit->weight_unit)->toBe(WeightUnit::Gram)
        ->and($explicit->purchase_date->toDateString())->toBe('2026-11-02')
        ->and($explicit->price_currency)->toBe('USD');
});

it('saves nothing on a dry run', function () {
    AurumServer::tool(CreateOrderTool::class, [
        'order_number' => 'DRY-1',
        'order_date' => '2026-11-01',
        'supplier_name' => 'Neuer Händler',
        'dry_run' => true,
        'items' => [
            ['name' => 'Silbermünze', 'material' => 'silver', 'type' => 'coin', 'weight' => 1, 'weight_unit' => 'troy_ounce', 'amount' => 10],
        ],
    ])
        ->assertOk()
        ->assertSee('DRY RUN')
        ->assertStructuredContentPath('dry_run', true)
        ->assertStructuredContentPath('order.id', null)
        ->assertStructuredContentPath('products.0.name', 'Silbermünze');

    AurumServer::tool(CreateProductTool::class, [
        'name' => 'Silbermünze', 'material' => 'silver', 'type' => 'coin', 'weight' => 1, 'weight_unit' => 'troy_ounce', 'dry_run' => true,
    ])->assertOk()->assertStructuredContentPath('product.id', null);

    expect(Order::query()->count())->toBe(0)
        ->and(Product::query()->count())->toBe(0)
        ->and(Supplier::query()->count())->toBe(0);
});

it('rejects a duplicate order number of the same user', function () {
    Order::factory()->create(['user_id' => $this->user->id, 'order_number' => '25822']);

    AurumServer::tool(CreateOrderTool::class, ['order_number' => '25822', 'supplier_name' => 'Anderer Händler'])
        ->assertHasErrors(['You already have an order with number 25822']);

    expect(Order::query()->where('user_id', $this->user->id)->count())->toBe(1)
        ->and(Supplier::query()->where('name', 'Anderer Händler')->exists())->toBeFalse();
});

it('allows the same order number for another user', function () {
    Order::factory()->create(['user_id' => $this->other->id, 'order_number' => '25822']);

    AurumServer::tool(CreateOrderTool::class, ['order_number' => '25822'])->assertOk();
});

it('rejects invalid enum values and rolls back the whole order', function () {
    AurumServer::tool(CreateOrderTool::class, [
        'order_number' => 'X-1',
        'items' => [
            ['name' => 'Ok', 'material' => 'gold', 'type' => 'coin', 'weight' => 1, 'weight_unit' => 'gram'],
            ['name' => 'Kaputt', 'material' => 'unobtainium', 'type' => 'coin', 'weight' => 1, 'weight_unit' => 'gram'],
        ],
    ])->assertHasErrors(['items.1.material']);

    AurumServer::tool(CreateProductTool::class, [
        'name' => 'Kaputt', 'material' => 'gold', 'type' => 'coin', 'weight' => 1, 'weight_unit' => 'gram', 'price_currency' => 'GBP',
    ])->assertHasErrors(['price currency']);

    expect(Order::query()->count())->toBe(0)->and(Product::query()->count())->toBe(0);
});

it('requires the core fields without a template', function () {
    AurumServer::tool(CreateProductTool::class, ['name' => 'Etwas'])
        ->assertHasErrors(['material', 'weight unit']);
});

it('does not accept another user\'s depository or supplier', function () {
    AurumServer::tool(CreateProductTool::class, [
        'name' => 'X', 'material' => 'gold', 'type' => 'coin', 'weight' => 1, 'weight_unit' => 'gram', 'depository_id' => $this->otherDepository->id,
    ])->assertHasErrors(['depository']);

    $foreignSupplier = Supplier::factory()->create(['user_id' => $this->other->id]);

    AurumServer::tool(CreateOrderTool::class, ['order_number' => 'S-1', 'supplier_id' => $foreignSupplier->id])
        ->assertHasErrors(['supplier']);
});

it('creates a product linked to an own order, defaulting the purchase date', function () {
    $order = Order::factory()->create(['user_id' => $this->user->id, 'order_date' => '2026-10-10']);

    AurumServer::tool(CreateProductTool::class, [
        'order_id' => $order->id, 'name' => 'Platinbarren', 'material' => 'platinum', 'type' => 'ingot', 'weight' => 1, 'weight_unit' => 'troy_ounce',
        'price' => 950.5, 'price_currency' => 'EUR',
    ])
        ->assertOk()
        ->assertStructuredContentPath('product.order_id', $order->id)
        ->assertStructuredContentPath('product.purchase_date', '2026-10-10')
        ->assertStructuredContentPath('product.material', 'platinum');

    expect($order->products()->count())->toBe(1);
});

it('cannot create a product in another user\'s order', function () {
    $foreign = Order::factory()->create(['user_id' => $this->other->id]);

    AurumServer::tool(CreateProductTool::class, [
        'order_id' => $foreign->id, 'name' => 'X', 'material' => 'gold', 'type' => 'coin', 'weight' => 1, 'weight_unit' => 'gram',
    ])->assertHasErrors(["Order #{$foreign->id} not found."]);

    expect(Product::query()->count())->toBe(0);
});

it('attaches a receipt to an own order', function () {
    Storage::fake('local');
    $order = Order::factory()->create(['user_id' => $this->user->id, 'receipts' => [['path' => 'receipts/old.pdf', 'label' => 'Alt']]]);

    AurumServer::tool(AttachOrderReceiptTool::class, [
        'order_id' => $order->id,
        'filename' => 'rechnung.png',
        'content_base64' => base64_encode(pngBytes()),
        'label' => 'Rechnung',
    ])
        ->assertOk()
        ->assertStructuredContentPath('receipt.label', 'Rechnung')
        ->assertStructuredContentCount(2, 'receipts');

    $receipts = $order->refresh()->receiptEntries();
    expect($receipts)->toHaveCount(2)
        ->and($receipts[1]['label'])->toBe('Rechnung')
        ->and($receipts[1]['path'])->toStartWith('receipts/')->toEndWith('.png')
        ->and($receipts[1]['path'])->not->toContain('rechnung');

    Storage::disk('local')->assertExists($receipts[1]['path']);
});

it('cannot attach a receipt to another user\'s order', function () {
    Storage::fake('local');
    $foreign = Order::factory()->create(['user_id' => $this->other->id]);

    AurumServer::tool(AttachOrderReceiptTool::class, [
        'order_id' => $foreign->id, 'filename' => 'r.png', 'content_base64' => base64_encode(pngBytes()),
    ])->assertHasErrors(["Order #{$foreign->id} not found."]);

    expect($foreign->refresh()->receipts)->toBeNull()
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('rejects disallowed receipt files', function () {
    Storage::fake('local');
    $order = Order::factory()->create(['user_id' => $this->user->id]);

    AurumServer::tool(AttachOrderReceiptTool::class, [
        'order_id' => $order->id, 'filename' => 'evil.php', 'content_base64' => base64_encode('<?php echo 1;'),
    ])->assertHasErrors(['Only these file types are allowed']);

    AurumServer::tool(AttachOrderReceiptTool::class, [
        'order_id' => $order->id, 'filename' => 'fake.pdf', 'content_base64' => base64_encode('<?php echo 1;'),
    ])->assertHasErrors(['is not a PDF or a supported image']);

    AurumServer::tool(AttachOrderReceiptTool::class, [
        'order_id' => $order->id, 'filename' => 'x.pdf', 'content_base64' => '***not base64***',
    ])->assertHasErrors(['not valid base64']);

    expect($order->refresh()->receipts)->toBeNull()
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('adds front and back images to an own product', function () {
    Storage::fake('public');
    $product = Product::factory()->create(['user_id' => $this->user->id, 'depository_id' => $this->depository->id, 'front_image_path' => null, 'back_image_path' => null]);

    AurumServer::tool(SetProductImageTool::class, [
        'product_id' => $product->id, 'side' => 'front', 'filename' => 'britannia-front.png', 'content_base64' => 'data:image/png;base64,'.base64_encode(pngBytes()),
    ])
        ->assertOk()
        ->assertStructuredContentPath('has_front_image', true)
        ->assertStructuredContentPath('has_back_image', false);

    AurumServer::tool(SetProductImageTool::class, [
        'product_id' => $product->id, 'side' => 'back', 'filename' => 'britannia-back.png', 'content_base64' => base64_encode(pngBytes()),
    ])->assertOk();

    $product->refresh();
    expect($product->front_image_path)->toStartWith('products/')->toEndWith('.png')
        ->and(str_contains((string) $product->front_image_path, 'britannia'))->toBeFalse()
        ->and($product->back_image_path)->toStartWith('products/')
        ->and($product->back_image_path === $product->front_image_path)->toBeFalse();

    Storage::disk('public')->assertExists([$product->front_image_path, $product->back_image_path]);
});

it('never replaces an existing product image', function () {
    Storage::fake('public');
    $product = Product::factory()->create(['user_id' => $this->user->id, 'depository_id' => $this->depository->id, 'front_image_path' => 'products/old.png']);

    AurumServer::tool(SetProductImageTool::class, [
        'product_id' => $product->id, 'side' => 'front', 'filename' => 'new.png', 'content_base64' => base64_encode(pngBytes()),
    ])->assertHasErrors(['already has a front image']);

    expect($product->refresh()->front_image_path)->toBe('products/old.png')
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('cannot add an image to another user\'s product', function () {
    Storage::fake('public');
    $foreign = Product::factory()->create(['user_id' => $this->other->id, 'depository_id' => $this->otherDepository->id, 'front_image_path' => null]);

    AurumServer::tool(SetProductImageTool::class, [
        'product_id' => $foreign->id, 'side' => 'front', 'filename' => 'x.png', 'content_base64' => base64_encode(pngBytes()),
    ])->assertHasErrors(["Product #{$foreign->id} not found."]);

    expect($foreign->refresh()->front_image_path)->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('rejects invalid product images and needs the images permission', function () {
    Storage::fake('public');
    $product = Product::factory()->create(['user_id' => $this->user->id, 'depository_id' => $this->depository->id, 'front_image_path' => null]);

    AurumServer::tool(SetProductImageTool::class, [
        'product_id' => $product->id, 'side' => 'top', 'filename' => 'x.png', 'content_base64' => base64_encode(pngBytes()),
    ])->assertHasErrors(['side must be "front"']);

    AurumServer::tool(SetProductImageTool::class, [
        'product_id' => $product->id, 'side' => 'front', 'filename' => 'evil.php', 'content_base64' => base64_encode('<?php echo 1;'),
    ])->assertHasErrors(['Only these file types are allowed']);

    AurumServer::tool(SetProductImageTool::class, [
        'product_id' => $product->id, 'side' => 'front', 'filename' => 'fake.png', 'content_base64' => base64_encode('%PDF-1.4 not an image'),
    ])->assertHasErrors(['is not a supported image']);

    mcpConnect($this->user, [McpPermission::Read, McpPermission::Create, McpPermission::Receipts]);

    AurumServer::tool(SetProductImageTool::class, [
        'product_id' => $product->id, 'side' => 'front', 'filename' => 'x.png', 'content_base64' => base64_encode(pngBytes()),
    ])->assertHasErrors();

    expect($product->refresh()->front_image_path)->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});
