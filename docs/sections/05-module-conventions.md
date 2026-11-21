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
