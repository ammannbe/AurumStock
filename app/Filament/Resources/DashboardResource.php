<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DashboardResource\Pages\Dashboard as DashboardPage;
use Filament\Resources\Resource;

class DashboardResource extends Resource
{
    // No model — this resource is a simple navigation wrapper for the dashboard page.
    protected static ?string $model = null;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static \UnitEnum|string|null $navigationGroup = 'User';

    public static function getPages(): array
    {
        return [
            'index' => DashboardPage::route('/'),
        ];
    }
}
