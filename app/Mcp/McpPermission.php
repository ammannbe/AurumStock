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

    public function label(): string
    {
        return match ($this) {
            McpPermission::Read => __('Read orders, products, suppliers and depositories'),
            McpPermission::Create => __('Create orders, products and suppliers'),
            McpPermission::Receipts => __('Attach receipts to orders'),
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
