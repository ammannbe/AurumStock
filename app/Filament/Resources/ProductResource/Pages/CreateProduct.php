<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResourceUrl();
    }

    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        $duplicateData = $this->prefillDataFromDuplicatedProduct();

        $this->form->fill();

        if ($duplicateData !== []) {
            $this->form->fill($duplicateData, shouldFillStateWithNull: false);
        }

        $this->callHook('afterFill');
    }

    /**
     * Values copied from another product when opened via the "Duplicate" table action.
     * Order/purchase details are excluded since the new product is a separate purchase.
     *
     * @return array<string, mixed>
     */
    protected function prefillDataFromDuplicatedProduct(): array
    {
        $productId = request()->query('duplicate');

        if (! $productId) {
            return [];
        }

        $product = Product::query()->whereKey($productId)->where('user_id', Auth::id())->first();

        if (! $product) {
            return [];
        }

        return collect($product->attributesToArray())
            ->except(['id', 'order_id', 'purchase_date', 'tax_year', 'source_hash', 'created_at', 'updated_at'])
            ->all();
    }
}
