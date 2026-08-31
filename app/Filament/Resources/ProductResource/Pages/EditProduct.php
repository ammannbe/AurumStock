<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProductResource\Widgets\ProductPriceHistoryChartWidget;
use App\Filament\UserResources\ProductTemplateResource;
use App\Models\Product;
use App\Models\ProductTemplate;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('templateSync')
                ->label(fn (Product $record): string => $record->isSynchronizedWithTemplate()
                    ? __('In sync with template')
                    : __('Template changed'))
                ->icon(fn (Product $record): string => $record->isSynchronizedWithTemplate()
                    ? 'heroicon-o-check-badge'
                    : 'heroicon-o-exclamation-triangle')
                ->color(fn (Product $record): string => $record->isSynchronizedWithTemplate() ? 'success' : 'warning')
                ->visible(fn (Product $record): bool => $record->template_id !== null)
                ->modalHeading(__('Template Comparison'))
                ->modalDescription(fn (Product $record): string => $record->isSynchronizedWithTemplate()
                    ? __('This product matches its template.')
                    : __('This product differs from its template. Review the changes below.'))
                ->modalContent(fn (Product $record) => view('filament.products.template-differences', [
                    'differences' => $record->templateDifferences(),
                ]))
                ->modalSubmitActionLabel(__('Sync Now'))
                ->modalSubmitAction(fn ($action, Product $record) => $record->templateDifferences() === [] ? false : $action)
                ->action(function (Product $record): void {
                    $record->syncFromTemplate();

                    Notification::make()
                        ->title(__('Product synchronized'))
                        ->body(__('The product was updated from its template.'))
                        ->success()
                        ->send();

                    $this->fillForm();
                }),
            Action::make('createTemplate')
                ->label(__('Create Template'))
                ->icon('heroicon-o-bookmark')
                ->color('gray')
                ->visible(fn (): bool => (bool) Auth::user()?->is_admin)
                ->modalHeading(__('Create Product Template'))
                ->modalDescription(__('Create a new product template from this product.'))
                ->fillForm(fn (Product $record): array => ['name' => $record->name])
                ->schema([
                    TextInput::make('name')
                        ->label(__('Template Name'))
                        ->required()
                        ->maxLength(255),
                ])
                ->action(function (Product $record, array $data): void {
                    $template = ProductTemplate::createFromProduct($record, $data['name']);

                    Notification::make()
                        ->title(__('Template Created'))
                        ->body(__('Product template ":name" created successfully.', ['name' => $template->name]))
                        ->success()
                        ->actions([
                            Action::make('edit')
                                ->label(__('Edit Template'))
                                ->url(ProductTemplateResource::getUrl('edit', ['record' => $template]))
                                ->button(),
                        ])
                        ->send();

                    $this->fillForm();
                }),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            ProductPriceHistoryChartWidget::class,
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
    }

    /**
     * @return array<string, mixed>
     */
    public function getWidgetData(): array
    {
        return [
            'record' => $this->getRecord(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Handle front/Back (value side) removals if toggled in the form
        /** @var Product $record */
        $record = $this->getRecord();

        if (! empty($data['remove_front_image']) && ! empty($record->front_image_path)) {
            Storage::disk('public')->delete($record->front_image_path);
            $data['front_image_path'] = null;
        }

        if (! empty($data['remove_back_image']) && ! empty($record->back_image_path)) {
            Storage::disk('public')->delete($record->back_image_path);
            $data['back_image_path'] = null;
        }

        // Clean up any transient remove flags
        unset($data['remove_front_image'], $data['remove_back_image']);

        return $data;
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResourceUrl();
    }
}
