<?php

namespace App\Filament\Widgets;

use App\Enums\Material;
use App\Support\CurrencyConverter;
use App\Support\StockPriceRange;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\View\WidgetsIconAlias;
use Filament\Widgets\Widget;

/**
 * Shell widget: renders the filter bar and one chart per material in a 2x2 grid.
 */
class StockPriceHistoryChartWidget extends Widget implements HasSchemas
{
    use InteractsWithSchemas;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $filters = [];

    protected string $view = 'filament.widgets.stock-price-history-chart';

    protected int|string|array $columnSpan = 'full';

    public function mount(): void
    {
        $this->filters = StockPriceRange::fromSession();
    }

    public function getHeading(): ?string
    {
        return __('Stock Price History').' ('.CurrencyConverter::preferredCode().')';
    }

    public function updatedFilters(mixed $value, ?string $key = null): void
    {
        $preset = $this->filters['preset'] ?? null;

        if ($key === 'preset' && is_string($preset) && $preset !== 'custom') {
            [$this->filters['startDate'], $this->filters['endDate']] = StockPriceRange::forPreset($preset);
        }

        // Editing a date by hand no longer matches the chosen preset.
        if (in_array($key, ['startDate', 'endDate'], true)) {
            $this->filters['preset'] = StockPriceRange::presetForDates(
                is_string($this->filters['startDate'] ?? null) ? $this->filters['startDate'] : null,
                is_string($this->filters['endDate'] ?? null) ? $this->filters['endDate'] : null,
            ) ?? 'custom';
        }

        session([StockPriceRange::SESSION_KEY => $this->filters]);
    }

    public function applyPreset(string $preset): void
    {
        [$start, $end] = StockPriceRange::forPreset($preset);

        $this->filters = ['preset' => $preset, 'startDate' => $start, 'endDate' => $end];

        session([StockPriceRange::SESSION_KEY => $this->filters]);
    }

    public function resetFilters(): void
    {
        $this->filters = StockPriceRange::defaults();

        session()->forget(StockPriceRange::SESSION_KEY);
    }

    /**
     * @return array<int, string>
     */
    public function getShorthandPresets(): array
    {
        return StockPriceRange::SHORTHAND_PRESETS;
    }

    public function getActivePreset(): ?string
    {
        $preset = $this->filters['preset'] ?? null;

        return is_string($preset) ? $preset : null;
    }

    /**
     * @return array<int, Material>
     */
    public function getMaterials(): array
    {
        return Material::cases();
    }

    public function getStartDate(): string
    {
        $value = $this->filters['startDate'] ?? null;

        return is_string($value) ? $value : StockPriceRange::defaults()['startDate'];
    }

    public function getEndDate(): string
    {
        $value = $this->filters['endDate'] ?? null;

        return is_string($value) ? $value : StockPriceRange::defaults()['endDate'];
    }

    public function getFiltersTriggerAction(): Action
    {
        return Action::make('filter')
            ->label(__('filament-widgets::chart.actions.filter.label'))
            ->iconButton()
            ->icon(FilamentIcon::resolve(WidgetsIconAlias::CHART_WIDGET_FILTER) ?? Heroicon::Funnel)
            ->color('gray')
            ->livewireClickHandlerEnabled(false);
    }

    public function getFiltersSchema(): Schema
    {
        if ((! $this->isCachingSchemas) && $this->hasCachedSchema('filtersSchema')) {
            return $this->getSchema('filtersSchema');
        }

        return $this->filtersSchema($this->makeSchema())
            ->statePath('filters')
            ->live();
    }

    public function filtersSchema(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('preset')
                    ->label(__('Period'))
                    ->options(StockPriceRange::options())
                    ->default(StockPriceRange::DEFAULT_PRESET)
                    ->selectablePlaceholder(false)
                    ->native(false)
                    ->columnSpanFull(),
                DatePicker::make('startDate')
                    ->label(__('Start Date'))
                    ->default(fn (): string => StockPriceRange::defaults()['startDate'])
                    ->native(false)
                    ->columnSpan(1),
                DatePicker::make('endDate')
                    ->label(__('End Date'))
                    ->default(fn (): string => StockPriceRange::defaults()['endDate'])
                    ->native(false)
                    ->columnSpan(1),
                Actions::make([
                    Action::make('resetFilters')
                        ->label(__('Reset'))
                        ->icon('heroicon-m-arrow-path')
                        ->color('gray')
                        ->link()
                        ->action(fn () => $this->resetFilters()),
                ])
                    ->alignEnd()
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }
}
