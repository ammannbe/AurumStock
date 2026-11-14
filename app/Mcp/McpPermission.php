<?php

namespace App\Mcp;

/**
 * What an AI client connected over MCP may do with the user's inventory. Chosen on the approval
 * screen and changeable under "Connected AI clients"; the user's own scope and policies still apply.
 */
enum McpPermission: string
{
    case Read = 'read';
    case Create = 'create';
    case Receipts = 'receipts';
    case Images = 'images';
    case ReplaceMedia = 'replace_media';

    public function label(): string
    {
        return match ($this) {
            McpPermission::Read => __('Read orders, products, suppliers and depositories'),
            McpPermission::Create => __('Create orders, products and suppliers'),
            McpPermission::Receipts => __('Attach receipts to orders'),
            McpPermission::Images => __('Add images, videos and missing inscriptions to products'),
            McpPermission::ReplaceMedia => __('Replace and remove product images, media and inscriptions'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (McpPermission $permission): array => [$permission->value => $permission->label()])
            ->all();
    }
}
