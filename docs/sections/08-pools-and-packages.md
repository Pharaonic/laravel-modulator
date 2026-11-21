## Pools And Packages

Pools are the directories modules are discovered in. When several pools hold the same identity, the pool with the highest priority wins and the other copies are shadowed.

| Pool | Path | Namespace | Priority |
| --- | --- | --- | --- |
| `local` | `Modules/` | `Modules` | 100 |
| `app` (1.x layout) | `app/Modules/` | `App\Modules` | 90 |
| Packages | declared by each package | `Modules` by default | 50 by default |

### Rules

- Equal priorities for the same identity are an error: the identity is not resolved until you change a priority or remove a copy.
- State is computed after selection: a disabled winner never falls back to a shadowed copy.
- Providers, routes, resources and classes come only from the selected copy.

### Shipping Modules In A Package

A Composer package declares its pools in its own `composer.json`. Modulator reads them from `vendor/composer/installed.json`; the package needs no service provider.

```json title="vendor/acme/shop/composer.json"
{
    "name": "acme/shop",
    "extra": {
        "pharaonic": {
            "modulator": {
                "pools": [
                    { "path": "modules" }
                ]
            }
        }
    }
}
```

Each pool accepts `path` (relative to the package), `namespace` (default `Modules`) and `priority` (default 50). The pool is named after the package (`acme/shop`, then `acme/shop:2` for a second pool).

:::danger Do not autoload package modules yourself
A package must not map its module namespaces in its own `autoload` section. Modulator autoloads the selected copy only; `module:cache` refuses Composer mappings that point at a shadowed copy.
:::

Inspect the pools and the copies of a module:

```bash title="Terminal" no-line-numbers
php artisan module:pools
php artisan module:source Shop
```
