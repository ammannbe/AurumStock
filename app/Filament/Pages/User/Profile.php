<?php

namespace App\Filament\Pages\User;

use BackedEnum;
use Filament\Pages\Page;

class Profile extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-user';

    protected static ?string $navigationLabel = 'Profile';

    protected static \UnitEnum|string|null $navigationGroup = 'Account';

    protected static ?string $slug = 'profile';

    protected string $view = 'filament.pages.user.profile';
}
