<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    /**
     * ID of the product this form was opened to duplicate, kept as a Livewire
     * property since request() query params aren't available on later requests.
     */
    public ?int $duplicatedProductId = null;

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
     * The image files are only copied in mutateFormDataBeforeCreate(), once the
     * product is actually saved, so an abandoned create form leaves no orphan files.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $duplicatedFilePaths = $this->duplicatedFilePaths();

        if (($data['front_image_path'] ?? null) === ($duplicatedFilePaths['front_image_path'] ?? null)) {
            $data['front_image_path'] = $this->duplicateStoredFile($data['front_image_path']);
        }

        if (($data['back_image_path'] ?? null) === ($duplicatedFilePaths['back_image_path'] ?? null)) {
            $data['back_image_path'] = $this->duplicateStoredFile($data['back_image_path']);
        }

        $duplicatedAssets = $duplicatedFilePaths['assets'] ?? [];
        $duplicatedAssetPaths = collect(is_array($duplicatedAssets) ? $duplicatedAssets : [])
            ->pluck('path')
            ->filter()
            ->all();

        if (is_array($data['assets'] ?? null)) {
            $data['assets'] = array_map(function (mixed $asset) use ($duplicatedAssetPaths): mixed {
                if (! is_array($asset) || ! is_string($asset['path'] ?? null)) {
                    return $asset;
                }

                if (! in_array($asset['path'], $duplicatedAssetPaths, true)) {
                    return $asset;
                }

                $asset['path'] = $this->duplicateStoredFile($asset['path']) ?? $asset['path'];

                return $asset;
            }, $data['assets']);
        }

        return $data;
    }

    /**
     * Re-reads the duplicated product's original paths from the query param rather than
     * caching them on the (non-public, so Livewire-volatile) page instance.
     *
     * @return array<string, mixed>
     */
    protected function duplicatedFilePaths(): array
    {
        $product = $this->duplicatedProduct();

        if (! $product) {
            return [];
        }

        return Arr::only($product->attributesToArray(), ['front_image_path', 'back_image_path', 'assets']);
    }

    /**
     * Values copied from another product when opened via the "Duplicate" table action.
     * Order/purchase details are excluded since the new product is a separate purchase.
     *
     * @return array<string, mixed>
     */
    protected function prefillDataFromDuplicatedProduct(): array
    {
        $product = $this->duplicatedProduct();

        if (! $product) {
            return [];
        }

        return collect($product->attributesToArray())
            ->except(['id', 'order_id', 'purchase_date', 'tax_year', 'source_hash', 'created_at', 'updated_at'])
            ->all();
    }

    protected function duplicatedProduct(): ?Product
    {
        $this->duplicatedProductId ??= (int) request()->query('duplicate');

        if (! $this->duplicatedProductId) {
            return null;
        }

        return Product::query()->whereKey($this->duplicatedProductId)->where('user_id', Auth::id())->first();
    }

    /**
     * Only duplicates a file that was carried over unchanged from the source product;
     * a freshly uploaded file for this form is left untouched.
     */
    protected function duplicateStoredFile(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return null;
        }

        $directory = pathinfo($path, PATHINFO_DIRNAME);
        $filename = pathinfo($path, PATHINFO_FILENAME);
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        $newPath = ($directory !== '.' ? $directory.'/' : '')
            .$filename.'-'.Str::random(8)
            .($extension !== '' ? '.'.$extension : '');

        $disk->copy($path, $newPath);

        return $newPath;
    }
}
