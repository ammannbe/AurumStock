<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\App;
use Tests\TestCase;

class SwissGermanTranslationTest extends TestCase
{
    public function test_swiss_german_inherits_the_german_filament_translations(): void
    {
        App::setLocale('de_CH');

        $this->assertSame('Filter', __('filament-tables::table.filters.heading'));
        $this->assertSame('Erstellen', __('filament-actions::create.single.label'));
        $this->assertSame('Abbrechen', __('filament-actions::modal.actions.cancel.label'));
    }

    public function test_swiss_german_replaces_sharp_s_with_double_s(): void
    {
        App::setLocale('de_CH');

        $this->assertSame('Schliessen', __('filament-actions::view.single.modal.actions.close.label'));
        $this->assertSame('Grösse', __('Size'));
    }

    public function test_german_keeps_the_sharp_s(): void
    {
        App::setLocale('de');

        $this->assertSame('Schließen', __('filament-actions::view.single.modal.actions.close.label'));
        $this->assertSame('Größe', __('Size'));
    }
}
