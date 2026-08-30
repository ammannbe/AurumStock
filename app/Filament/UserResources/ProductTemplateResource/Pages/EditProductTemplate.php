<?php

namespace App\Filament\UserResources\ProductTemplateResource\Pages;

use App\Filament\UserResources\ProductTemplateResource;
use Filament\Resources\Pages\EditRecord;

class EditProductTemplate extends EditRecord
{
    protected static string $resource = ProductTemplateResource::class;

    protected function getRedirectUrl(): ?string
    {
        return $this->getResourceUrl();
    }
}
