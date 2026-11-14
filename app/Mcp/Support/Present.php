<?php

namespace App\Mcp\Support;

use App\Models\Depository;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductTemplate;
use App\Models\Supplier;
use Illuminate\Support\Str;

/**
 * Compact array representations of models for MCP tool output.
 */
class Present
{
    /**
     * @return array<string, mixed>
     */
    public static function product(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'material' => self::enumName($product->material),
            'type' => self::enumName($product->type),
            'weight' => (float) $product->weight,
            'weight_unit' => self::enumName($product->weight_unit),
            'fineness' => $product->getAttribute('fineness'),
            'year' => $product->getAttribute('year'),
            'amount' => $product->amount,
            'price' => $product->getAttribute('price') !== null ? (float) $product->getAttribute('price') : null,
            'price_currency' => $product->getAttribute('price_currency'),
            'purchase_date' => $product->getAttribute('purchase_date')?->toDateString(),
            'depository_id' => $product->getAttribute('depository_id'),
            'order_id' => $product->getAttribute('order_id'),
            'template_id' => $product->template_id,
            'has_front_image' => filled($product->getAttribute('front_image_path')),
            'has_back_image' => filled($product->getAttribute('back_image_path')),
            'media_count' => count(array_filter(is_array($product->assets) ? $product->assets : [], 'is_array')),
            'text_front' => $product->getAttribute('text_front'),
            'text_back' => $product->getAttribute('text_back'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function order(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'order_date' => $order->order_date?->toDateString(),
            'supplier_id' => $order->supplier_id,
            'supplier_name' => $order->supplier?->name,
            'receipts_count' => count($order->receiptEntries()),
        ];
    }

    /**
     * @return array<int, array{label: string|null, filename: string}>
     */
    public static function receipts(Order $order): array
    {
        return array_map(fn (array $entry): array => [
            'label' => $entry['label'],
            'filename' => basename($entry['path']),
        ], $order->receiptEntries());
    }

    /**
     * @return array<string, mixed>
     */
    public static function supplier(Supplier $supplier): array
    {
        return [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'website' => $supplier->getAttribute('website'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function depository(Depository $depository): array
    {
        return [
            'id' => $depository->getKey(),
            'name' => $depository->getAttribute('name'),
            'description' => $depository->getAttribute('description'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function template(ProductTemplate $template): array
    {
        return [
            'id' => $template->getKey(),
            'display_name' => $template->displayName(),
            'name' => $template->getAttribute('name'),
            'material' => self::enumName($template->getAttribute('material')),
            'type' => self::enumName($template->getAttribute('type')),
            'weight' => $template->getAttribute('weight') !== null ? (float) $template->getAttribute('weight') : null,
            'weight_unit' => self::enumName($template->getAttribute('weight_unit')),
            'fineness' => $template->getAttribute('fineness'),
            'year' => $template->getAttribute('year'),
            'manufacturer' => $template->getAttribute('manufacturer'),
            'country_of_origin' => $template->getAttribute('country_of_origin'),
        ];
    }

    public static function enumName(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? Str::snake($value->name) : $value;
    }
}
