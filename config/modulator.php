<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Disabled modules
    |--------------------------------------------------------------------------
    |
    | Every discovered module is enabled unless its identity is listed here.
    | Disabling a parent module disables its children too. Keep this a plain
    | list of strings: module:enable and module:disable edit it in place.
    |
    */

    'disabled' => [
        // 'Shop/Orders',
        // 'Analytics',
    ],

    /*
    |--------------------------------------------------------------------------
    | Pools
    |--------------------------------------------------------------------------
    |
    | Directories that hold modules. When several pools hold the same module,
    | the highest priority wins and the others are shadowed. "app" holds 1.x
    | modules (app/Modules, App\Modules). Packages declare their own pools in
    | their composer.json ("extra.pharaonic.modulator.pools", priority 50).
    | A pool may also be the class name of a Pharaonic\Laravel\Modulator\Contracts\Pool.
    |
    */

    'pools' => [
        'local' => [
            'path' => base_path('Modules'),
            'namespace' => 'Modules',
            'priority' => 100,
        ],
        'app' => [
            'path' => app_path('Modules'),
            'namespace' => 'App\\Modules',
            'priority' => 90,
            'legacy' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Discovery
    |--------------------------------------------------------------------------
    |
    | With the cache on, the discovery result is kept in
    | bootstrap/cache/modulator.php and rebuilt by module:discover and
    | module:cache, or when the pools or the installed packages change.
    |
    */

    'discovery' => [
        'cache' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Generation
    |--------------------------------------------------------------------------
    |
    | The pool new and published modules are written to, and an optional
    | directory of custom stubs. When "stubs" is null, stubs/modulator is used
    | if it exists (php artisan vendor:publish --tag=modulator-stubs).
    |
    */

    'generation' => [
        'pool' => 'local',
        'stubs' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Vite
    |--------------------------------------------------------------------------
    |
    | The entry points a module has, relative to the module. The ones that
    | exist are the inputs module:vite lists for vite.config.js, and the
    | entries @moduleVite('Shop') renders.
    |
    */

    'vite' => [
        'entries' => ['resources/css/app.css', 'resources/js/app.js'],
    ],

];
