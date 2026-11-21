<?php

namespace Pharaonic\Laravel\Modulator\Tests\Fixtures\Filament;

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\ServiceProvider;

/**
 * Two Filament panels, as an application's panel providers register them:
 * the default "admin" panel and an "app" panel.
 */
class PanelsProvider extends ServiceProvider
{
    public function register(): void
    {
        Filament::registerPanel(fn (): Panel => Panel::make()->default()->id('admin')->path('admin'));
        Filament::registerPanel(fn (): Panel => Panel::make()->id('app')->path('app'));
    }
}
