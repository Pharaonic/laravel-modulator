<?php

namespace Modules\Shop\Providers;

use Illuminate\Support\ServiceProvider;

final class ShopServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->instance('shop.provider', 'package');
    }
}
