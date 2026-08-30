<?php

namespace App\Filament\Resources\DepositoryResource\Pages;

use App\Filament\Resources\DepositoryResource;
use Filament\Resources\Pages\EditRecord;

class EditDepository extends EditRecord
{
    protected static string $resource = DepositoryResource::class;

    protected function getRedirectUrl(): ?string
    {
        return $this->getResourceUrl();
    }
}
