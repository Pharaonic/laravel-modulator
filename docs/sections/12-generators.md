## Generators

`module:make` creates a module; `module:make:*` creates classes inside one. Generators take `{module} {name}`, or only `{name}` to use the module selected with `module:use`.

```bash title="Terminal" no-line-numbers
php artisan module:make Shop
php artisan module:make Shop/Orders --plain
php artisan module:make:controller Shop ProductController --resource --model=Product
php artisan module:make:model Shop Product --all
php artisan module:make:migration Shop create_products_table --create=products
```

### Generator Types

There is a `module:make:*` command for every `make:*` command of Laravel.

| Command | Writes to | Options |
| --- | --- | --- |
| `module:make:action` | `app/Actions` | |
| `module:make:cast` | `app/Casts` | `--inbound` |
| `module:make:channel` | `app/Broadcasting` | |
| `module:make:class` | `app` | `--invokable` |
| `module:make:command` | `app/Console/Commands` | `--command=`, tests |
| `module:make:component` | `app/View/Components` + view | `--inline`, `--view` (anonymous: only the view), `--path=`, tests |
| `module:make:config` | `config` | |
| `module:make:controller` | `app/Http/Controllers` | `--api`, `--type=`, `--invokable`, `--model=`, `--parent=`, `--resource`, `--requests`, tests |
| `module:make:event` | `app/Events` | |
| `module:make:exception` | `app/Exceptions` | `--render`, `--report` |
| `module:make:factory` | `database/factories` | `--model=` |
| `module:make:interface` | `app/Contracts` | |
| `module:make:job` | `app/Jobs` | `--sync`, tests |
| `module:make:job-middleware` | `app/Jobs/Middleware` | tests |
| `module:make:listener` | `app/Listeners` | `--event=`, `--queued`, tests |
| `module:make:livewire` | `app/Http/Livewire` + `resources/views/livewire` | `--inline`, tests (`Livewire::test`); needs `livewire/livewire` |
| `module:make:mail` | `app/Mail` | `--markdown[=view]`, `--view[=view]`, tests |
| `module:make:middleware` | `app/Http/Middleware` | tests |
| `module:make:migration` | `database/migrations` | `--create=`, `--table=`, `--fullpath` (runs Laravel's `make:migration`) |
| `module:make:model` | `app/Models` | `--all`, `--controller`, `--factory`, `--migration`, `--morph-pivot`, `--policy`, `--seed`, `--pivot`, `--resource`, `--api`, `--requests`, tests |
| `module:make:notification` | `app/Notifications` | `--markdown[=view]`, tests |
| `module:make:observer` | `app/Observers` | `--model=` |
| `module:make:policy` | `app/Policies` | `--model=`, `--guard=` |
| `module:make:provider` | `app/Providers` | |
| `module:make:request` | `app/Http/Requests` | |
| `module:make:resource` | `app/Http/Resources` | `--collection` |
| `module:make:rule` | `app/Rules` | `--implicit` |
| `module:make:scope` | `app/Models/Scopes` | |
| `module:make:seeder` | `database/seeders` | |
| `module:make:service` | `app/Services` | |
| `module:make:test` | `tests/Feature` or `tests/Unit` | `--unit`, `--pest`, `--phpunit` |
| `module:make:trait` | `app/Concerns` (traits are concerns, as in Laravel) | |
| `module:make:view` | `resources/views` | `--extension=`, tests |

"Tests" means `--test`, `--pest` and `--phpunit`: they also write a matching test, e.g. `tests/Feature/Http/Controllers/ProductControllerTest.php`. Pest is used when it is installed, unless you pass `--phpunit`; `--pest` without Pest installed fails and writes nothing.

### Framework Table Migrations

| Command | Table |
| --- | --- |
| `module:make:cache-table` | `cache` |
| `module:make:notifications-table` | `notifications` |
| `module:make:queue-failed-table` | `failed_jobs` |
| `module:make:queue-table` | `jobs` |
| `module:make:session-table` | `sessions` |

Each writes the migration into the module's `database/migrations`, with the table name from your configuration and the columns of the running Laravel version.

Every generator accepts `--force` to overwrite an existing file. Names may contain sub-namespaces: `Admin/ProductController`. Legacy (1.x) modules keep the 1.x folders (`Commands`, `Interfaces`, `Traits`, `Scopes`, `Classes`).

:::warning
Generators never write into package modules. Publish the module first with `module:publish`.
:::

### Custom Stubs

Publish the package stubs to `stubs/modulator`, then edit the ones you want. A file there replaces the package stub with the same name (`controller.stub`, `model.stub`, `module/provider.stub`, `module/routes-web.stub`, ...); delete the ones you keep as they are, and they keep coming from the package.

```bash title="Terminal" no-line-numbers
php artisan vendor:publish --tag=modulator-stubs
```

`stubs/modulator` is used automatically while `generation.stubs` is `null`. To keep the stubs elsewhere, point `generation.stubs` at that directory:

```php title="config/pharaonic/modulator.php"
'generation' => [
    'pool' => 'local',
    'stubs' => base_path('resources/stubs/modules'),
],
```

The `modulator-stubs` and `pharaonic-stubs` tags publish the stubs; the `pharaonic` and `laravel-modulator` tags do not, so publishing a package's files never adds them.

```php title="stubs/modulator/service.stub"
<?php

namespace {{ namespace }};

final class {{ class }}
{
    //
}
```

Tokens: `{{ namespace }}`, `{{ class }}`, `{{ module }}`, `{{ slug }}`, `{{ rootNamespace }}`, and per type `{{ model }}`, `{{ namespacedModel }}`, `{{ modelVariable }}`, `{{ command }}`, `{{ view }}`. Module stubs use `{{ namespace }}`, `{{ name }}`, `{{ module }}` and `{{ slug }}`.

### Factories

Module models use their module factories with `HasFactory` (Laravel 8+): `Modules\Shop\Models\Product` uses `Modules\Shop\Database\Factories\ProductFactory`. On Laravel 7, module `database/factories` closures are loaded.
