<?php

namespace App\Filament\UserResources;

use App\Filament\Resources\SupplierResource as BaseSupplierResource;
use App\Filament\Resources\SupplierResource\Pages\CreateSupplier;
use App\Filament\Resources\SupplierResource\Pages\EditSupplier;
use App\Filament\Resources\SupplierResource\Pages\ListSuppliers;

/**
 * Thin wrapper so the SupplierResource can be discovered in the user panel namespace.
 */
class SupplierResource extends BaseSupplierResource
{
    public static function getPages(): array
    {
        return [
            'index' => ListSuppliers::route('/'),
            'create' => CreateSupplier::route('/create'),
            'edit' => EditSupplier::route('/{record}/edit'),
        ];
    }
}
