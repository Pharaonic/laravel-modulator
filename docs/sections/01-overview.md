:::badges
- Laravel Package {color=blue}
- {release.label} {color=green}
- {package.license} License {color=purple}
- AI Ready {color=violet}
:::

# Modulator

Modulator splits a Laravel application into modules that are found by convention. A directory with an app/ folder is a module: its classes autoload with no composer.json, its provider, routes, views, translations, config and migrations load the Laravel way, and Composer packages can ship modules that your application overrides with a local copy.

:::success AI Ready
Modulator ships Laravel Boost resources: a guideline loaded in every session and a `modulator-development` skill loaded when your agent works on modules. Run `php artisan boost:install` and Claude Code, Cursor, Codex, Copilot or Gemini CLI create modules and classes with the right `module:*` commands and namespaces.
:::

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

### Generators {icon="terminal"}
module:make plus a module:make command for every Laravel make command, with stubs you can replace.

### Namespaced Resources {icon="tag"}
Views, translations and config are namespaced by the module slug, as in shop::index and modules.shop.billing.

### AI Ready {icon="bot"}
Laravel Boost guideline and skill included, so php artisan boost:install teaches your coding agent the package.
:::

:::info Quick Tip
Run php artisan module:make Shop, then php artisan module:make:model Shop Product, and use Modules\Shop\Models\Product right away.
:::
