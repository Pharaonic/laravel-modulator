---
name: modulator-development
description: Creating, changing and wiring modules with pharaonic/laravel-modulator — module layout, generators, nested and package modules, enabling and disabling, migrations, tests, and the Livewire, Filament, executor and Vite integrations. Use when adding or changing code inside Modules/, or running module:* commands.
---

# Modulator Development

## When to use this skill

Use this skill when you add or change code in a module (`Modules/<Name>`), create a module or a class in one, move code between modules, or run a `module:*` command.

## Setup

The package needs no setup: `Modules/` is the default pool. The configuration is `pharaonic.modulator`; publish it with `php artisan vendor:publish --tag=modulator-config` (written to `config/pharaonic/modulator.php`) only to change pools, disabled modules or Vite entries.

## Module layout

| Path in `Modules/Shop` | Loaded as |
| --- | --- |
| `app/` | `Modules\Shop\` (PSR-4, no `app` segment) |
| `app/Providers/ShopServiceProvider.php` | Registered automatically (the only provider that is) |
| `config/*.php` | `config('modules.shop.<file>')` |
| `resources/views` | `view('shop::name')` |
| `lang` or `resources/lang` | `__('shop::file.key')` |
| `database/migrations`, `database/factories`, `database/seeders` | Run by `migrate`; `Modules\Shop\Database\Factories\`, `...\Seeders\` |
| `routes/web.php`, `routes/api.php` | `web` group; `api` group with the `api` prefix (no other URL prefix) |
| `routes/console.php`, `app/Console/Commands` | Console |
| `tests` | `Modules\Shop\Tests\` |
| `module.php` | Optional: `['dependencies' => ['Catalog'], 'exposes' => ['Contracts']]` |
| `Modules/<Child>` | A submodule, identity `Shop/<Child>`, namespace `Modules\Shop\<Child>` |

Models use their module factories through `HasFactory`: `Modules\Shop\Models\Product` uses `Modules\Shop\Database\Factories\ProductFactory`.

## Commands

Every command that takes a module accepts a nested identity (`Commerce/Checkout`). Without one, an interactive console uses the module selected with `module:use Shop`.

- `module:make Shop` creates a module (`--plain` for an empty one); `module:make Shop/Orders` a submodule.
- `module:make:<type> Shop <Name>`: action, cache-table, cast, channel, class, command, component, config, controller (`--resource`, `--api`, `--model=`, `--parent=`, `--requests`, `--singleton`, `--creatable`, `--invokable`), enum (`--string`, `--int`; uses `Pharaonic\SmartEnum\SmartEnum`), event, exception, factory, interface, job (`--sync`, `--batched`), job-middleware, listener, mail, middleware, migration (`--create=`, `--table=`), model (`--all`, `--migration`, `--factory`, `--seed`, `--policy`, `--controller`, `--resource`), notification, notifications-table, observer, policy, provider, queue-batches-table, queue-failed-table, queue-table, request, resource, rule, scope, seeder, service, session-table, test (`--unit`, `--pest`, `--phpunit`), trait, view. Most take `--test` / `--pest` / `--phpunit` to also write a test.
- `module:list`, `module:status Shop`, `module:enable Shop` (`--with-dependencies`), `module:disable Shop` (`--cascade`).
- `module:migrate Shop`, `module:migrate:rollback`, `:status`, `:reset`, `:refresh`, `module:seed Shop --class=`. They only touch that module's migrations; there is no module `migrate:fresh`.
- `module:test Shop`, `module:routes Shop --json`, `module:check Shop` (boundaries), `module:doctor`, `module:graph`.
- `module:publish Shop` copies a package module into `Modules/`, where it wins over the package copy.
- `module:cache` / `module:clear` (also run by `optimize` / `optimize:clear`).

## PHP API

```php
use Pharaonic\Laravel\Modulator\Facades\Modules;

$module = Modules::find('Shop');      // ?Module
$module->id();                        // "Shop"
$module->slug();                      // "shop"
$module->namespace();                 // "Modules\Shop"
$module->path('routes/web.php');      // absolute path
$module->isEnabled();
$module->package();                   // ?string, the Composer package of a package module

module_path('Shop', 'routes/web.php');
module_resource_path('Shop', 'views');
```

## Integrations

Each one works only when its package is installed; the package never requires them.

- Livewire 3: components in `app/Livewire` are registered as `<slug>::<kebab path>` (`Modules\Shop\Livewire\Admin\UserTable` is `shop::admin.user-table`). Create one with `module:make:livewire Shop Cart` (`--inline`).
- Filament 3: `app/Filament/{Resources,Pages,Widgets}` join the default panel, `app/Filament/<PanelId>/{Resources,Pages,Widgets}` the panel with that id (`app/Filament/App/Pages` is the `app` panel's). Create a resource with `module:make:filament-resource Shop Product` (`--simple`, `--view`, `--soft-deletes`, `--generate`, `--model=`).
- laravel-executor: a module's `executors/` folder is added to the executor pool. Create one with `module:make:executor Shop ImportCountries`.
- Vite: a module's existing `resources/css/app.css` and `resources/js/app.js` (`pharaonic.modulator.vite.entries`) are listed by `module:vite`. Add them to the app build with `...modules()` from `./vendor/pharaonic/laravel-modulator/resources/vite/modulator.js` in `vite.config.js`, and load them in a view with `@moduleVite('Shop')`.

## Pitfalls

- Never edit a module under `vendor/`; publish it first.
- Only the module provider named `<Name>ServiceProvider` is registered; register any other provider from it.
- Module routes have no automatic URL prefix.
- A disabled module loads nothing: no routes, views, migrations, providers, Livewire or Filament classes.
- Disabling or enabling a module needs `php artisan queue:restart` (and `octane:reload`) for long-running processes, and a Vite rebuild.
- `module:make:livewire`, `module:make:filament-resource` and `module:make:executor` refuse until their package is installed.

## Testing

Module tests live in `Modules/<Name>/tests` (`Modules\Shop\Tests\`); generated tests extend the application's `Tests\TestCase` (Pest tests bind it with `uses(Tests\TestCase::class)`). Run them with `php artisan module:test Shop`, which returns the runner's exit code. Factories and migrations of enabled modules are available in tests as in the application.
