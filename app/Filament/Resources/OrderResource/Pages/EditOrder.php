<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getRedirectUrl(): ?string
    {
        return $this->getResourceUrl();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var Order $record */
        $record = $this->getRecord();

        $receipts = $data['receipts'] ?? null;

        $record->deleteRemovedReceipts(is_array($receipts) ? $receipts : null);

        return $data;
    }
}
