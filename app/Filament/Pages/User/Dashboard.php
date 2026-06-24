<?php

namespace App\Filament\Pages\User;

use BackedEnum;
use Filament\Pages\Page;

class Dashboard extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = null;

    protected static ?string $slug = 'dashboard';

    protected string $view = 'filament.pages.user.dashboard';

    public static function getNavigationLabel(): string
    {
        return __('Dashboard');
    }
}
