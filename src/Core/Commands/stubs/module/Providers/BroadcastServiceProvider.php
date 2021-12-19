<?php

namespace App\Modules\{{ module-name }}\Providers;

use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        require module_path(AppServiceProvider::$module, 'routes/channels.php');
    }
}
