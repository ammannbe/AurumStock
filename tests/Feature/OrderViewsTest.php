<?php

namespace Tests\Feature;

use App\Filament\Resources\OrderResource;
use App\Filament\Resources\OrderResource\Pages\CreateOrder;
use App\Filament\Resources\OrderResource\Pages\EditOrder;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Depository;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class OrderViewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_manage_orders(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get('/app/orders')->assertStatus(200);
        $this->get('/app/orders/create')->assertStatus(200);
    }

    public function test_guests_cannot_access_orders(): void
    {
        $this->get('/app/orders')->assertRedirect();
    }

    public function test_a_duplicate_order_number_is_rejected_with_a_form_error(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create(['user_id' => $user->id]);
        Order::factory()->create([
            'user_id' => $user->id,
            'supplier_id' => $supplier->id,
            'order_number' => '25822',
        ]);

        $this->actingAs($user);

        Livewire::test(CreateOrder::class)
            ->fillForm([
                'order_number' => '25822',
                'supplier_id' => $supplier->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['order_number']);

        $this->assertSame(1, Order::query()->count());
    }

    public function test_a_concurrent_duplicate_insert_surfaces_as_a_form_error(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $component = Livewire::test(CreateOrder::class)
            ->fillForm([
                'order_number' => '25822',
                'supplier_id' => $supplier->id,
            ]);

        // Simulate another request winning the race after form validation passed.
        Order::factory()->create([
            'user_id' => $user->id,
            'supplier_id' => $supplier->id,
            'order_number' => '25822',
        ]);

        $component->call('create')->assertHasFormErrors(['order_number']);

        $this->assertSame(1, Order::query()->count());
    }

    public function test_inline_order_creation_rejects_a_duplicate_order_number(): void
    {
        $user = User::factory()->create();
        Depository::factory()->create(['user_id' => $user->id]);
        $supplier = Supplier::factory()->create(['user_id' => $user->id]);
        Order::factory()->create([
            'user_id' => $user->id,
            'supplier_id' => $supplier->id,
            'order_number' => '25822',
        ]);

        $this->actingAs($user);

        Livewire::test(CreateProduct::class)
            ->callAction(
                TestAction::make('createOption')->schemaComponent('order_id'),
                ['order_number' => '25822', 'supplier_id' => $supplier->id],
            )
            ->assertHasActionErrors(['order_number']);

        $this->assertSame(1, Order::query()->count());
    }

    public function test_the_same_order_number_is_allowed_for_a_different_supplier(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create(['user_id' => $user->id]);
        $otherSupplier = Supplier::factory()->create(['user_id' => $user->id]);
        Order::factory()->create([
            'user_id' => $user->id,
            'supplier_id' => $supplier->id,
            'order_number' => '25822',
        ]);

        $this->actingAs($user);

        Livewire::test(CreateOrder::class)
            ->fillForm([
                'order_number' => '25822',
                'supplier_id' => $otherSupplier->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(2, Order::query()->count());
    }

    public function test_another_user_can_reuse_an_order_number(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ownerSupplier = Supplier::factory()->create(['user_id' => $owner->id]);
        Order::factory()->create([
            'user_id' => $owner->id,
            'supplier_id' => $ownerSupplier->id,
            'order_number' => '25822',
        ]);

        $otherSupplier = Supplier::factory()->create(['user_id' => $other->id]);

        $this->actingAs($other);

        Livewire::test(CreateOrder::class)
            ->fillForm([
                'order_number' => '25822',
                'supplier_id' => $otherSupplier->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(2, Order::query()->count());
    }

    public function test_inline_order_creation_allows_a_fresh_order_number(): void
    {
        $user = User::factory()->create();
        Depository::factory()->create(['user_id' => $user->id]);
        $supplier = Supplier::factory()->create(['user_id' => $user->id]);
        Order::factory()->create([
            'user_id' => $user->id,
            'supplier_id' => $supplier->id,
            'order_number' => '25822',
        ]);

        $this->actingAs($user);

        Livewire::test(CreateProduct::class)
            ->callAction(
                TestAction::make('createOption')->schemaComponent('order_id'),
                ['order_number' => '25823', 'supplier_id' => $supplier->id],
            )
            ->assertHasNoActionErrors();

        $this->assertSame(2, Order::query()->count());
    }

    public function test_editing_an_order_does_not_collide_with_itself(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create(['user_id' => $user->id]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'supplier_id' => $supplier->id,
            'order_number' => '25822',
        ]);

        $this->actingAs($user);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->fillForm(['order_date' => '2026-09-11'])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    public function test_users_only_see_their_own_orders(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        Order::factory()->create(['user_id' => $owner->id, 'order_number' => 'FOREIGN-1']);
        Order::factory()->create(['user_id' => $other->id, 'order_number' => 'OWN-1']);

        $this->actingAs($other);

        $response = $this->get('/app/orders');
        $response->assertOk();
        $response->assertSee('OWN-1');
        $response->assertDontSee('FOREIGN-1');
    }

    public function test_order_options_are_scoped_to_the_current_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        Order::factory()->create(['user_id' => $owner->id, 'order_number' => 'FOREIGN-1']);
        $own = Order::factory()->create(['user_id' => $other->id, 'order_number' => 'OWN-1']);

        $this->actingAs($other);

        $this->assertSame([$own->id], array_keys(OrderResource::optionsForCurrentUser()));
    }

    public function test_products_sharing_an_order_see_the_same_receipts(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'receipts' => [['path' => 'receipts/invoice.pdf', 'label' => 'Invoice']],
        ]);

        $first = Product::factory()->create(['user_id' => $user->id, 'order_id' => $order->id]);
        $second = Product::factory()->create(['user_id' => $user->id, 'order_id' => $order->id]);

        /** @var Order $firstOrder */
        $firstOrder = $first->order;
        /** @var Order $secondOrder */
        $secondOrder = $second->order;

        $this->assertSame($firstOrder->receiptEntries(), $secondOrder->receiptEntries());
        $this->assertSame('Invoice', $firstOrder->receiptEntries()[0]['label']);
    }

    public function test_linking_products_assigns_and_unassigns_them(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $linked = Product::factory()->create(['user_id' => $user->id, 'order_id' => $order->id]);
        $free = Product::factory()->create(['user_id' => $user->id, 'order_id' => null]);

        $this->actingAs($user);

        Livewire::test(ListOrders::class)
            ->callAction(TestAction::make('linkProducts')->table($order), ['product_ids' => [$free->id]])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('products', ['id' => $free->id, 'order_id' => $order->id]);
        $this->assertDatabaseHas('products', ['id' => $linked->id, 'order_id' => null]);
    }

    public function test_the_products_count_links_to_the_filtered_product_list(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $inOrder = Product::factory()->create(['user_id' => $user->id, 'order_id' => $order->id]);
        $notInOrder = Product::factory()->create(['user_id' => $user->id, 'order_id' => null]);

        $this->actingAs($user);

        /** @var ListOrders $listOrders */
        $listOrders = Livewire::test(ListOrders::class)->instance();

        $url = $listOrders->getTable()->getColumn('products_count')->record($order)->getUrl();

        $query = [];
        parse_str((string) parse_url((string) $url, PHP_URL_QUERY), $query);

        // The link is only useful if the list page actually binds these params.
        Livewire::withQueryParams($query)
            // @phpstan-ignore argument.templateType
            ->test(ListProducts::class)
            ->assertCanSeeTableRecords([$inOrder])
            ->assertCanNotSeeTableRecords([$notInOrder]);
    }

    public function test_linking_products_cannot_steal_another_users_product(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $foreign = Product::factory()->create(['user_id' => $other->id, 'order_id' => null]);

        $this->actingAs($user);

        Livewire::test(ListOrders::class)
            ->callAction(TestAction::make('linkProducts')->table($order), ['product_ids' => [$foreign->id]]);

        $this->assertDatabaseHas('products', ['id' => $foreign->id, 'order_id' => null]);
    }

    public function test_deleting_an_order_keeps_its_products(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $product = Product::factory()->create(['user_id' => $user->id, 'order_id' => $order->id]);

        $order->delete();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'order_id' => null]);
    }

    public function test_assigning_an_order_prefills_the_purchase_date(): void
    {
        $user = User::factory()->create();
        Depository::factory()->create(['user_id' => $user->id]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'order_date' => '2026-03-04',
        ]);

        $this->actingAs($user);

        Livewire::test(CreateProduct::class)
            ->fillForm(['purchase_date' => null])
            ->fillForm(['order_id' => $order->id])
            ->assertSchemaStateSet(['purchase_date' => '2026-03-04']);
    }

    public function test_assigning_an_order_keeps_an_existing_purchase_date(): void
    {
        $user = User::factory()->create();
        Depository::factory()->create(['user_id' => $user->id]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'order_date' => '2026-03-04',
        ]);

        $this->actingAs($user);

        Livewire::test(CreateProduct::class)
            ->fillForm(['purchase_date' => '2020-01-01'])
            ->fillForm(['order_id' => $order->id])
            ->assertSchemaStateSet(['purchase_date' => '2020-01-01']);
    }

    public function test_receipt_uploads_are_stored_on_the_private_disk(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'receipts' => null]);

        $this->actingAs($user);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->fillForm([
                'receipts' => [
                    ['path' => [UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf')], 'label' => 'Invoice'],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $stored = $order->fresh()->receiptEntries();

        $this->assertCount(1, $stored);
        Storage::disk('local')->assertExists($stored[0]['path']);
        Storage::disk('public')->assertMissing($stored[0]['path']);
    }

    public function test_a_user_cannot_edit_another_users_order(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($other);

        $this->get('/app/orders/'.$order->getRouteKey().'/edit')->assertNotFound();
    }

    public function test_owner_can_stream_a_receipt_for_preview(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('receipts/invoice.pdf', '%PDF-1.4 fake');

        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'receipts' => [['path' => 'receipts/invoice.pdf', 'label' => 'Invoice']],
        ]);

        $this->actingAs($user)
            ->get(route('orders.receipts.show', ['order' => $order->id, 'index' => 0]))
            ->assertOk();
    }

    public function test_pdf_receipts_are_served_without_a_sandbox_csp(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('receipts/invoice.pdf', '%PDF-1.4 fake');

        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'receipts' => [['path' => 'receipts/invoice.pdf', 'label' => 'Invoice']],
        ]);

        $response = $this->actingAs($user)
            ->get(route('orders.receipts.show', ['order' => $order->id, 'index' => 0]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        // The sandbox directive stops Chrome from rendering the PDF inline.
        $this->assertStringNotContainsString('sandbox', $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
    }

    public function test_unexpected_file_types_are_forced_to_download(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('receipts/note.html', '<script>alert(1)</script>');

        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'receipts' => [['path' => 'receipts/note.html', 'label' => 'Note']],
        ]);

        $response = $this->actingAs($user)
            ->get(route('orders.receipts.show', ['order' => $order->id, 'index' => 0]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/octet-stream');
        $this->assertStringContainsString('sandbox', $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
    }

    public function test_a_user_cannot_stream_another_users_receipt(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('receipts/invoice.pdf', '%PDF-1.4 fake');

        $owner = User::factory()->create();
        $other = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $owner->id,
            'receipts' => [['path' => 'receipts/invoice.pdf', 'label' => 'Invoice']],
        ]);

        $this->actingAs($other)
            ->get(route('orders.receipts.show', ['order' => $order->id, 'index' => 0]))
            ->assertForbidden();
    }

    public function test_guests_cannot_stream_receipts(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'receipts' => [['path' => 'receipts/invoice.pdf', 'label' => 'Invoice']],
        ]);

        $this->get(route('orders.receipts.show', ['order' => $order->id, 'index' => 0]))
            ->assertRedirect();
    }

    public function test_an_unknown_receipt_index_returns_not_found(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'receipts' => null]);

        $this->actingAs($user)
            ->get(route('orders.receipts.show', ['order' => $order->id, 'index' => 5]))
            ->assertNotFound();
    }

    public function test_previewable_receipts_flag_pdfs_and_images(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'receipts' => [
                ['path' => 'receipts/invoice.pdf', 'label' => 'Invoice'],
                ['path' => 'receipts/photo.jpg', 'label' => null],
            ],
        ]);

        $previewable = $order->previewableReceipts();

        $this->assertTrue($previewable[0]['is_pdf']);
        $this->assertFalse($previewable[0]['is_image']);
        $this->assertTrue($previewable[1]['is_image']);
        $this->assertFalse($previewable[1]['is_pdf']);
    }

    public function test_product_edit_page_shows_a_preview_action_per_receipt(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'receipts' => [
                ['path' => 'receipts/invoice.pdf', 'label' => 'Invoice'],
                ['path' => 'receipts/photo.jpg', 'label' => 'Photo'],
            ],
        ]);
        $product = Product::factory()->create(['user_id' => $user->id, 'order_id' => $order->id]);

        $this->actingAs($user);

        $this->get('/app/products/'.$product->getRouteKey().'/edit')
            ->assertOk()
            ->assertSee('Invoice')
            ->assertSee('Photo');
    }

    public function test_product_edit_page_without_receipts_shows_a_hint(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'receipts' => null]);
        $product = Product::factory()->create(['user_id' => $user->id, 'order_id' => $order->id]);

        $this->actingAs($user);
        $this->app->setLocale('en');

        $this->get('/app/products/'.$product->getRouteKey().'/edit')
            ->assertOk()
            ->assertSee('No receipts on this order yet.');
    }

    public function test_receipt_preview_view_renders_pdf_and_image_markup(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'receipts' => [
                ['path' => 'receipts/invoice.pdf', 'label' => 'Invoice'],
                ['path' => 'receipts/photo.jpg', 'label' => 'Photo'],
            ],
        ]);

        $html = view('filament.orders.receipt-preview', [
            'receipts' => $order->previewableReceipts(),
        ])->render();

        $this->assertStringContainsString('type="application/pdf"', $html);
        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString(route('orders.receipts.show', ['order' => $order->id, 'index' => 0]), $html);
        $this->assertStringContainsString('Invoice', $html);
    }

    public function test_order_edit_page_loads_with_receipts(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('receipts/invoice.pdf', '%PDF-1.4 fake');

        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'receipts' => [['path' => 'receipts/invoice.pdf', 'label' => 'Invoice']],
        ]);

        $this->actingAs($user);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])->assertOk();

        $this->assertSame('Invoice', $order->previewableReceipts()[0]['label']);
    }

    public function test_the_receipts_column_opens_a_preview_of_every_receipt(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('receipts/invoice.pdf', '%PDF-1.4 fake');
        Storage::disk('local')->put('receipts/photo.jpg', 'fake');

        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'receipts' => [
                ['path' => 'receipts/invoice.pdf', 'label' => 'Invoice'],
                ['path' => 'receipts/photo.jpg', 'label' => 'Photo'],
            ],
        ]);

        $this->actingAs($user);

        /** @var ListOrders $page */
        $page = Livewire::test(ListOrders::class)
            ->mountAction(TestAction::make('previewReceipts')->table($order))
            ->instance();

        $html = (string) $page->getMountedAction()?->getModalContent()?->render();

        $this->assertStringContainsString('Invoice', $html);
        $this->assertStringContainsString('Photo', $html);
        $this->assertStringContainsString('type="application/pdf"', $html);
        $this->assertStringContainsString(route('orders.receipts.show', ['order' => $order->id, 'index' => 0]), $html);
        $this->assertStringContainsString(route('orders.receipts.show', ['order' => $order->id, 'index' => 1]), $html);
    }

    public function test_the_receipts_preview_explains_when_there_are_none(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'receipts' => null]);

        $this->actingAs($user);

        /** @var ListOrders $page */
        $page = Livewire::test(ListOrders::class)
            ->mountAction(TestAction::make('previewReceipts')->table($order))
            ->instance();

        $content = $page->getMountedAction()?->getModalContent();

        $this->assertStringContainsString(__('No receipts on this order yet.'), (string) $content?->render());
    }

    public function test_inline_supplier_creation_stamps_the_current_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $supplier = Supplier::create(['name' => 'SwissGoldShop', 'user_id' => $user->id]);

        $this->assertDatabaseHas('suppliers', ['name' => 'SwissGoldShop', 'user_id' => $user->id]);
        $this->assertSame($user->id, $supplier->user_id);
    }
}
