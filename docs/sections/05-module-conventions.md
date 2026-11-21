## Module Conventions

A module is a directory under a pool that contains `app/`. A directory without `app/` is a namespace group: it holds modules but has no provider, routes or state.

```text title="Layout" no-copy
Modules/
├── Shop/                         Shop (module)
│   ├── app/                      Modules\Shop\
│   │   ├── Models/Product.php    Modules\Shop\Models\Product
│   │   └── Providers/ShopServiceProvider.php
│   ├── database/{migrations,factories,seeders}/
│   ├── resources/{views,lang}/
│   ├── routes/{web.php,api.php}
│   ├── tests/
│   ├── helpers.php               optional
│   ├── module.php                optional
│   ├── module.lock               only in a published copy (module:publish)
│   └── Modules/                  only when it has child modules
│       └── Orders/app/           Shop/Orders → Modules\Shop\Orders\
└── Platform/                     namespace group (no app/)
    └── Billing/app/              Platform/Billing → Modules\Platform\Billing\
```

### Identities

- An identity is one or more StudlyCase segments joined by `/`: `Shop`, `Commerce/Checkout`.
- Each segment starts with an uppercase letter and holds letters and digits only. `Modules` is reserved.
- At most 16 levels.
- The namespace is the pool namespace followed by the segments: `Commerce/Checkout` is `Modules\Commerce\Checkout`.
- An identity is stable across pools: a package module and its local copy share it, and the state is keyed by it.

### The Service Provider

A module has at most one conventional provider, `app/Providers/<Name>ServiceProvider.php`, where `<Name>` is the last identity segment. A module without one is valid. Register any other provider from it.

:::warning
Two identities that differ only by case (`Shop` and `SHOP`) are rejected, since they cannot both exist on a case-insensitive filesystem.
:::

### Helpers

A module can define global functions in `helpers.php` at its root (next to `app/`, not inside it). The file is required once, when the module registers, and only while the module is enabled. A child module loads its own `helpers.php`.

```php title="Modules/Shop/helpers.php"
<?php

if (! function_exists('shop_currency')) {
    function shop_currency(): string
    {
        return config('modules.shop.store.currency', 'EGP');
    }
}
```

- Functions are global. Prefix them with the module slug and wrap each one in `function_exists()`, so two modules cannot collide with a fatal "Cannot redeclare" error.
- The file loads after the application's config files, so a helper cannot be used inside `config/*.php`. It is available in providers, routes, views and everything after them.
- Disabling a module stops loading its helpers the next time the application boots. A function already defined in a running process (a queue worker, Octane) stays until it restarts.

### Livewire Components

With `livewire/livewire` installed (Livewire 3 or 4), the components in a module's `app/Livewire` are registered for you, named after the module slug. Nothing to configure, and nothing happens without Livewire.

| Class | Name |
| --- | --- |
| `Modules\Shop\Livewire\Cart` | `shop::cart` |
| `Modules\Shop\Livewire\Admin\UserTable` | `shop::admin.user-table` |
| `Modules\Shop\Orders\Livewire\OrderList` | `shop-orders::order-list` |

```blade title="Blade"
<livewire:shop::cart />
@livewire('shop::admin.user-table')
```

- Only enabled modules register components.
- On Livewire 3, abstract classes and classes that are not `Livewire\Component` are skipped, and the classes come from the module class map, so a cached manifest (`module:cache`) needs no scan.
- On Livewire 4, the module slug is a Livewire component namespace for `app/Livewire`, so Livewire finds `shop::admin.user-table` itself.
- Create one with `module:make:livewire` (see Generators).
- Livewire 3 only for now: install `livewire/livewire:^3.6`. Livewire 4 is not supported yet.

### Filament Resources, Pages And Widgets

With `filament/filament` installed (Filament 3), a module's Filament classes join your panels on their own, by directory, the way Filament lays out an application:

| Directory | Joins |
| --- | --- |
| `app/Filament/Resources`, `app/Filament/Pages`, `app/Filament/Widgets` | the default panel |
| `app/Filament/<PanelId>/Resources`, `.../Pages`, `.../Widgets` | the panel with that id, in StudlyCase: `app/Filament/App/Pages` is the `app` panel's |

Nothing goes in your panel providers. The resources' own pages, relation managers and widgets come with them.

- Only enabled modules add classes; abstract classes, and classes that are not a resource, page or widget, are skipped.
- Only classes directly in each directory count: `Resources/ProductResource/Pages/*` are the resource's pages, not standalone pages.
- A panel is the default one when `->default()` comes before `->id()` in its provider, as in the provider Filament generates.
- After `php artisan filament:cache-components`, panels use the cache: run it again when module classes change.
- Create a resource with `module:make:filament-resource` (see Generators).
- Filament 3 only for now: install `filament/filament:^3.3`. Filament 4 and 5 are not supported yet.

### Executors

With `pharaonic/laravel-executor` installed, a module's `executors` folder joins the executor pool, so `php artisan execute`, `execute:rollback` and `execute:status` run its executors with the application's.

```bash title="Terminal" no-line-numbers
php artisan module:make:executor Shop ImportCountries
php artisan execute
```

- Only enabled modules with an `executors` folder are added.
- Executor names are unique across every folder, the application's included.

### Vite Assets

Module assets are part of the application's single Vite build. A module's entry points are the files of `pharaonic.modulator.vite.entries` that exist in it, by default `resources/css/app.css` and `resources/js/app.js`.

Add the entries of every enabled module to the inputs in `vite.config.js`, using the helper the package ships (no npm package to install):

```js title="vite.config.js"
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { modules } from './vendor/pharaonic/laravel-modulator/resources/vite/modulator.js';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', ...modules()],
            refresh: true,
        }),
    ],
});
```

`modules()` runs `php artisan module:vite` once, when Vite loads the config. Pass `{ php: '/usr/bin/php8.2' }` to use another PHP binary (or set `PHP_BINARY`).

Then load a module's assets in its views:

```blade title="Blade"
@moduleVite('Shop')
@moduleVite('Shop', 'resources/js/admin.js')
@moduleVite('Shop', ['resources/js/admin.js', 'resources/css/admin.css'])
```

- `@moduleVite('Shop')` renders Laravel's `@vite` tags for the module's existing entries, and nothing when it has none. Given entries are paths inside the module, rendered as they are, so they must also be Vite inputs (add them to `pharaonic.modulator.vite.entries`, or to `input` by hand).
- Paths are relative to the project root, as Vite's manifest names them (`Modules/Shop/resources/js/app.js`), so package modules in `vendor/` work the same.
- Disabled modules are left out of `module:vite`. Enabling or disabling a module changes the inputs, so rebuild (`npm run build`), or restart `npm run dev`.

