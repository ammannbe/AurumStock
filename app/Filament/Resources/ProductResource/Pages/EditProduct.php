<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Handle front/back image removals if toggled in the form
        /** @var Product $record */
        $record = $this->getRecord();

        if (! empty($data['remove_front_image']) && ! empty($record->front_image_path)) {
            Storage::disk('public')->delete($record->front_image_path);
            $data['front_image_path'] = null;
        }

        if (! empty($data['remove_back_image']) && ! empty($record->back_image_path)) {
            Storage::disk('public')->delete($record->back_image_path);
            $data['back_image_path'] = null;
        }

        // Clean up any transient remove flags
        unset($data['remove_front_image'], $data['remove_back_image']);

        return $data;
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResourceUrl();
    }
}
