<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

// The application's own route provider, as every Laravel 6–10 application has:
// it loads bootstrap/cache/routes.php when routes are cached.
class RouteServiceProvider extends ServiceProvider
{
    public function map() {}
}
