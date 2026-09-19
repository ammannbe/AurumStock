<?php

namespace App\Filament\UserResources;

use App\Filament\Resources\OrderResource as BaseOrderResource;
use App\Filament\Resources\OrderResource\Pages\CreateOrder;
use App\Filament\Resources\OrderResource\Pages\EditOrder;
use App\Filament\Resources\OrderResource\Pages\ListOrders;

/**
 * Thin wrapper so the OrderResource can be discovered in the user panel namespace.
 */
class OrderResource extends BaseOrderResource
{
    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'create' => CreateOrder::route('/create'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }
}
