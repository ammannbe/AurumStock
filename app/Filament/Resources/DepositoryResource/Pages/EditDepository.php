<?php

namespace App\Filament\Resources\DepositoryResource\Pages;

use App\Filament\Resources\DepositoryResource;
use App\Models\Depository;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditDepository extends EditRecord
{
    protected static string $resource = DepositoryResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        /** @var Depository $record */
        $record = $this->getRecord();

        $count = $record->shares()->count();

        if ($count === 0) {
            return __('Not shared');
        }

        return __('Shared with :count users', ['count' => $count]);
    }

    protected function getHeaderActions(): array
    {
        return [
            DepositoryResource::makeEditSharesAction(),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResourceUrl();
    }
}
