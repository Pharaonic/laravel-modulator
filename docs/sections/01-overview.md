:::badges
- Laravel Package {color=blue}
- {release.label} {color=green}
- {package.license} License {color=purple}
:::

# Modulator

Modulator splits a Laravel application into modules that are found by convention. A directory with an app/ folder is a module: its classes autoload with no composer.json, its provider, routes, views, translations, config and migrations load the Laravel way, and Composer packages can ship modules that your application overrides with a local copy.

:::features
### Convention Discovery {icon="search"}
A folder with app/ under Modules/ is a module. No manifest, no composer.json per module.

### Nested Modules {icon="folder"}
Commerce/Checkout/Payments: child modules at any depth, with namespaces that follow the path.

### Package Modules {icon="package"}
Composer packages declare module pools; publish a module to override it locally under the same identity.

### Every Composer Mode {icon="bolt"}
Tested with the default, optimized and authoritative Composer autoloaders.

### Safe State Changes {icon="shield-check"}
module:enable and module:disable edit your config file in place and refuse what they cannot edit safely.

### Architecture Checks {icon="check-circle"}
module:doctor, module:graph and module:check find broken sources, cycles and undeclared module dependencies.
:::

:::info Quick Tip
Run php artisan module:make Shop, then php artisan module:make:model Shop Product, and use Modules\Shop\Models\Product right away.
:::
