## Upgrading From 1.x

Version 10.0.0 replaces 1.x on Laravel 10 (each Laravel major has its own line, from 6.0.0 on Laravel 6). Existing `app/Modules` modules keep working through the `app` pool (1.x layout). Move them to the new layout when you want; nothing is rewritten for you.

### Required Changes

1. Require the new version: `composer require pharaonic/laravel-modulator:^10.0` (use the line of your Laravel major).
2. `App\Modules\ServiceProvider` (the base class 1.x generated) must extend `Illuminate\Support\ServiceProvider`: `Core\ServiceProvider` is removed, and Modulator loads module resources itself.
3. Replace the removed helpers: `modules()` → `Modules::all()`, `getModuleDirectories()` → `Modules::find($id)->children()`, `studlyToSlug()` → `Modules::find($id)->slug()`, `getFiles()` → the `File` facade.
4. If you published the 1.x config, publish the new one (`--tag=modulator-config`); it lives in `config/pharaonic/modulator.php`.
5. On deployment, add `php artisan module:cache` after `composer install`.

### Before And After

| 1.x | 10.0.0 |
| --- | --- |
| `app/Modules/Shop`, `App\Modules\Shop\...` | `Modules/Shop/app`, `Modules\Shop\...` (1.x layout still supported) |
| A module is a folder with `Providers/` | A module is a folder with `app/` (the `app` pool keeps the 1.x rule) |
| Every provider in `Providers/` registered | One conventional `<Name>ServiceProvider` (1.x modules: every provider, as before) |
| `module:make Shop` → `app/Modules/Shop` | `module:make Shop` → `Modules/Shop`, new layout |
| `module:make:*` → 1.x folders | Laravel folders in new modules; 1.x folders in 1.x modules |
| `module:migrate:fresh` | Removed: use `module:migrate:refresh` |
| `module:make:translatable` | Removed: use `module:make:model` and `module:make:migration` with the laravel-translatable docs |
| `module:make:enum` | `module:make:enum`, whose enums use `Pharaonic\SmartEnum\SmartEnum` (`pharaonic/php-smart-enum` is installed with the package) |
| `module:routes` by name prefix | By real ownership |
| `module:seed Shop` → `App\Modules\Shop\database\seeders\DatabaseSeeder` | Same for 1.x modules; `Modules\Shop\Database\Seeders\DatabaseSeeder` for new ones |
| `modules()`, `getFiles()`, `getModuleDirectories()`, `studlyToSlug()` | Removed |
| Disabled state: none | `pharaonic.modulator.disabled`, `module:enable`, `module:disable` |

### Moving A Module To The New Layout

There is no automatic conversion: a namespace change touches your code. For each module:

1. `php artisan module:make Shop --plain` creates `Modules/Shop` with `app/Providers/ShopServiceProvider.php`.
2. Move the class folders (`Http`, `Models`, ...) into `Modules/Shop/app`, and `database`, `resources`, `routes`, `config`, `lang` to `Modules/Shop`.
3. Replace `App\Modules\Shop\` with `Modules\Shop\` in the module and in the code that uses it; database classes become `Modules\Shop\Database\Factories\` and `Modules\Shop\Database\Seeders\`.
4. Remove the module's own `RouteServiceProvider`: `routes/web.php` and `routes/api.php` load automatically. Move other provider code into `ShopServiceProvider`.
5. Move `Commands/` to `app/Console/Commands` with the `Modules\Shop\Console\Commands` namespace.
6. Delete `app/Modules/Shop`, then run `php artisan module:doctor` and your tests.

:::warning
Keep only one copy at a time: `Modules/Shop` (priority 100) shadows `app/Modules/Shop` (priority 90), so a half-moved module runs from the new copy only.
:::
