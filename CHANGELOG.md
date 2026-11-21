# Changelog

All notable changes to this project are documented here.

## 6.0.0 - Unreleased

### Added

- Convention-based discovery: a directory with `app/` in a pool is a module (`Modules/Shop/app/Models/Product.php` is `Modules\Shop\Models\Product`), with no `composer.json` or manifest per module.
- Nested modules at any depth (`Commerce/Checkout/Payments`) and namespace groups (directories without `app/`).
- Pools: `local` (`Modules/`, priority 100), the 1.x `app` pool (`app/Modules/`, priority 90), pools declared by Composer packages in `extra.pharaonic.modulator.pools` (priority 50), and custom `Contracts\Pool` classes.
- Deterministic source selection: the highest priority wins and lower sources are shadowed; equal priorities are reported and not resolved; a disabled winner never falls back to a shadowed source.
- `pharaonic.modulator.disabled`: every module is enabled unless listed; a disabled parent disables its children; missing, disabled or cyclic dependencies (optional `module.php`) disable the modules that need them, with a reason.
- Autoloading without Composer changes: module class maps built at discovery and PSR-4 prefixes, verified with the default, optimized (`-o`) and authoritative (`-a`) Composer autoloaders.
- Detection of Composer mappings that would load a shadowed copy of a module class.
- Module resources loaded the Laravel way: provider `Providers/<Name>ServiceProvider`, `config/`, views, translations (including JSON), migrations and `routes/web.php` / `routes/api.php`, with each route tagged with its module.
- `--pest` fails, and writes nothing, when `vendor/bin/pest` does not exist, instead of writing a test that cannot run.
- A `helpers.php` at the module root is required once when an enabled module registers, as in 1.x.
- After a change to the modules, `module:enable`, `module:disable`, `module:publish` and `module:delete` say which processes to restart, including `octane:reload` when Octane is installed.
- `Facades\Modules` (`all()`, `enabled()`, `find()`) and the `Module` value object.
- `module:discover`, `module:cache` (fails on any error), `module:clear`, and `module:publish` (copies a package module into the local pool, with `--dry-run`, `--force` and a backup of the replaced copy, `--with-children`).
- `php artisan optimize` also caches the module manifest, and `optimize:clear` clears it.
- `module:enable` and `module:disable` edit the `disabled` list of `config/pharaonic/modulator.php` in place, keeping the rest of the file, and refuse a computed list. Enabling cannot bypass a disabled parent or dependency (`--with-parents`, `--with-dependencies`); disabling a module others depend on needs `--cascade`.
- `module:status` shows the configured and the effective state, with the chain of reasons.
- `module:use` and `module:current`: a module selected for your console, used when a command leaves the module out, on interactive consoles outside CI and production only.
- `module:list`, `module:pools`, `module:source` (the active and shadowed copies, and why the source won), `module:graph` (text or JSON), `module:doctor` and `module:check` (static check of the class references between modules against `dependencies` and `exposes`).
- A `module:make:*` command for every `make:*` command of Laravel: action, cache-table, channel, class, command, config, controller, event, exception, factory, interface, job, job-middleware, listener, mail, middleware, migration, model, notification, notifications-table, observer, policy, provider, queue-failed-table, queue-table, request, resource, rule, scope, seeder, service, session-table, test, trait and view, with Laravel's options (nested and request-backed controllers, pivot models, Markdown mail, implicit rules, matching tests...). Stubs can be replaced (`generation.stubs`).
- `module:migrate:rollback`, `module:migrate:status`, `module:migrate:reset` and `module:migrate:refresh`, each limited to the module's migrations.
- Module commands in `app/Console/Commands` and `routes/console.php` are registered in the console; on Laravel 6 and 7, `database/factories` closures are loaded.
- `HasFactory` resolves module factories: `Modules\Shop\Models\Product` uses `Modules\Shop\Database\Factories\ProductFactory`.

### Changed

- The configuration is merged under `pharaonic.modulator` and published to `config/pharaonic/modulator.php` (tags `pharaonic`, `laravel-modulator`, `pharaonic-config`, `modulator-config`).
- The module manifest is `bootstrap/cache/modulator.php`; it is rebuilt when pools, installed packages or discovered directories change.
- `module_path()` and the `module_*_path()` helpers resolve through the registry and reject invalid identities.
- `module:make` creates the module in the generation pool (`Modules/<Name>`, new layout) instead of `app/Modules`, and nests a child in its parent's `Modules/` folder.
- `module:make:*` writes into `app/` with Laravel's directories (`Console/Commands`, `Contracts`, `Concerns`, `Models/Scopes`); legacy modules keep the 1.x directories (`Commands`, `Interfaces`, `Traits`, `Scopes`, `Classes`). Generators refuse package modules: publish them first.
- `module:delete` asks for confirmation (or `--force`), offers `--dry-run`, refuses modules others depend on, modules with children (without `--with-children`), package modules and anything outside its pool. A symlink or Windows junction inside a deleted, published or generated module is removed as a link, never followed.
- `module:seed` resolves `--class` in the module seeders namespace and fails when the class does not exist.
- `module:routes` lists the routes a module owns (the module its route file was loaded for, or the module of the controller), not the routes whose name starts with its slug.
- `module:test` runs Pest or PHPUnit with an argument list and returns their exit code.

### Deprecated

- The `config` and `modulator` publish tags, and `module_path()` without a module.

### Removed

- `module:migrate:fresh` now fails with an explanation: Laravel's `migrate:fresh` drops every table of the database, not only a module's. Use `module:migrate:refresh <module>`, or `migrate:fresh` for the whole database.
- `Pharaonic\Laravel\Modulator\Core\ServiceProvider`. A 1.x application changes one line: `App\Modules\ServiceProvider` extends `Illuminate\Support\ServiceProvider`; Modulator loads module resources itself.
- `module:make:translatable`, which wrote a `pharaonic/laravel-translatable` model with its translation model and migrations. Create them with `module:make:model` and `module:make:migration`, following the laravel-translatable docs.
- `module:make:cast` and `module:make:component` (Laravel 7), `module:make:queue-batches-table` and `module:make:job --batched` (Laravel 8), and `module:make:controller --singleton` / `--creatable` (Laravel 9) on the 6.x line: Laravel 6 has no custom casts, Blade components, job batches or singleton routes, so they could not run. Each returns on the first line whose Laravel supports it.
- `module:make:enum` on the 6.x line. Enums need PHP 8.1 and this line runs on PHP 8.0, so the command could not run. It returns, with enums using `pharaonic/php-smart-enum`, from the first line whose PHP versions can install it.
- The camelCase 1.x helpers `modules()`, `getFiles()`, `getModuleDirectories()` and `studlyToSlug()`. Use `Modules::all()`, `Modules::find($id)->children()` / `->slug()`, and the `File` facade.

### Fixed

- Module resources and the Eloquent factory resolver are no longer set up in a provider constructor; the legacy factory resolver no longer recurses for models outside the modules.
- A stale provider manifest no longer hides or breaks modules, and discovery never writes into `app/Modules` on a request.

### Compatibility

- Requires PHP 8.0 and Laravel 6.20.
