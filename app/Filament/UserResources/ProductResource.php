<?php

namespace App\Filament\UserResources;

use App\Filament\Resources\ProductResource as BaseProductResource;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;

class ProductResource extends BaseProductResource
{
    // Thin wrapper so the ProductResource can be discovered in the user panel namespace.
    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
