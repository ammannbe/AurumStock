<?php

namespace App\Filament\Pages\User;

use App\Enums\Material;
use App\Models\Exchange;
use App\Models\StockPrice;
use Carbon\Carbon;
use BackedEnum;
use Filament\Pages\Page;

class Dashboard extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $slug = 'dashboard';

    protected string $view = 'filament.pages.user.dashboard';

}
