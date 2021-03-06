<?php

namespace App\Modules\{{ module-name-namespace }}\Providers;

use App\Modules\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Module's name
     *
     * @var string
     */
    public static $module = "{{ module-name }}";

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // 
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
        //
    }
}
