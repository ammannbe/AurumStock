<?php

namespace App\Filament\Resources\DepositoryResource\Pages;

use App\Filament\Resources\DepositoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDepositories extends ListRecords
{
    protected static string $resource = DepositoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
