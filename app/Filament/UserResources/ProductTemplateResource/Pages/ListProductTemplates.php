<?php

namespace App\Filament\UserResources\ProductTemplateResource\Pages;

use App\Filament\UserResources\ProductTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductTemplates extends ListRecords
{
    protected static string $resource = ProductTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
