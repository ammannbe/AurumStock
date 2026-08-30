<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductTemplate;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the ordered media list shown in the preview slider, combining the
 * front and back images with the free-form `assets` entries.
 */
class ProductMediaGallery
{
    /**
     * @return array<int, array{url: string, type: string, label: string}>
     */
    public static function for(Product|ProductTemplate $record): array
    {
        $items = [];

        if (filled($record->front_image_path)) {
            $items[] = [
                'url' => Storage::disk('public')->url($record->front_image_path),
                'type' => 'image',
                'label' => __('Front (image side)'),
            ];
        }

        if (filled($record->back_image_path)) {
            $items[] = [
                'url' => Storage::disk('public')->url($record->back_image_path),
                'type' => 'image',
                'label' => __('Back (value side)'),
            ];
        }

        foreach (self::assets($record) as $asset) {
            if (! is_array($asset) || ! filled($asset['path'] ?? null) || ! is_string($asset['path'])) {
                continue;
            }

            $type = ($asset['type'] ?? null) === 'video' ? 'video' : 'image';
            $label = $asset['label'] ?? null;

            $items[] = [
                'url' => Storage::disk('public')->url($asset['path']),
                'type' => $type,
                'label' => is_string($label) && filled($label)
                    ? $label
                    : ($type === 'video' ? __('Video') : __('Image')),
            ];
        }

        return $items;
    }

    /**
     * @return array<int, mixed>
     */
    private static function assets(Product|ProductTemplate $record): array
    {
        $assets = $record->assets;

        return is_array($assets) ? array_values($assets) : [];
    }
}
