# Maintaining This Package

This file records package-specific maintainer facts that are safe to keep in the public repository.

## Purpose

Laravel-native modules: convention-based discovery of modules in pools (application directories and Composer packages), nested modules, deterministic source selection with local overrides of package modules, configuration-based enable/disable state, and autoloading that works with optimized and authoritative Composer class maps, without a `composer.json` per module.

## Supported Laravel lines

One branch per Laravel major, from `6.x` to the newest major. Each line's PHP range, Laravel constraint, and tools follow the Pharaonic Laravel line table. 1.x (Laravel 8–12) is replaced by these lines; `6.0.0` is the first release of the new numbering.

## Core library

None.

## Public API invariants

Configuration keys (`pharaonic.modulator.*`), publish tags, and registered Blade, Artisan, macro, and validation names are public API.

- Configuration: `pharaonic.modulator.disabled`, `pharaonic.modulator.pools` (arrays with `path`, `namespace`, `priority`, `legacy`, or a `Contracts\Pool` class name), `pharaonic.modulator.discovery.cache`, `pharaonic.modulator.generation.pool`, and `pharaonic.modulator.generation.stubs`.
- Package pools: `extra.pharaonic.modulator.pools` in a package `composer.json` (a list of `{path, namespace?, priority?}`; default namespace `Modules`, default priority 50; pool name `vendor/package`, then `vendor/package:2`…).
- Module conventions: a module is a directory with `app/` (legacy pools: `Providers/`); children live in `Modules/`; a `Modules` folder never adds an identity segment; identities are StudlyCase segments joined by `/`; the namespace is the pool namespace plus the segments; `Database\Factories\`, `Database\Seeders\` and `Tests\` map to `database/factories`, `database/seeders` and `tests`; the provider is `Providers/<Name>ServiceProvider`; the slug (views, translations, `modules.<slug>.*` configuration) keeps the 1.x algorithm.
- Optional `module.php` returns an array with `dependencies` (identities) and `exposes` (namespaces relative to the module).
- PHP API: `Facades\Modules` (`all()`, `enabled()`, `find()`), `Module`, `Contracts\Pool`, `DirectoryPool`, and the helpers `module_path()`, `module_config_path()`, `module_database_path()`, `module_resource_path()`.
- Commands and their signatures: `module:make`, `module:delete`, `module:list`, `module:enable`, `module:disable`, `module:status`, `module:use`, `module:current`, `module:pools`, `module:source`, `module:publish`, `module:discover`, `module:cache`, `module:clear`, `module:doctor`, `module:graph`, `module:check`, `module:migrate`, `module:migrate:rollback`, `module:migrate:status`, `module:migrate:reset`, `module:migrate:refresh`, `module:migrate:fresh` (refuses), `module:seed`, `module:test`, `module:routes`, and one `module:make:<type>` per `make:*` command of the newest Laravel (`src/Console/Make`), with its options. A new Laravel `make:*` command or option gets its module counterpart on every line. Generators take `{module} {name}`, or `{name}` with the module from `module:use`. Exit codes are 0 on success and 1 on refusal; `module:test` returns the test runner's code.
- Stub names (`stubs/*.stub`, `stubs/module/*.stub`) and their `{{ token }}` placeholders: an application's `generation.stubs` directory overrides them by name.
- The CLI context lives in `storage/framework/modulator/context/<user>.json` and is read only on interactive consoles outside CI (`CI` set) and production.
- Publish tags: `pharaonic`, `laravel-modulator`, `pharaonic-config`, and `modulator-config`. The 1.x tags `config` and `modulator` stay as deprecated aliases.
- Deprecated and kept until the first line that supports PHP 8.6: `module_path()` without a module. Removed in 6.0.0: `Core\ServiceProvider` and the camelCase 1.x helpers `modules()`, `getFiles()`, `getModuleDirectories()`, `studlyToSlug()`.

## Code layout

- `src/` root: the public API (`Module`, `ModuleRegistry`, `DirectoryPool`, `Contracts\Pool`, `Facades\Modules`, the service provider, `helpers.php`).
- `src/Discovery`: pools, discovery, class maps and the manifest. `src/Autoload`: the class loader and the factory resolver.
- `src/Actions`: what a command changes or checks (`EnableModule`, `DisableModule`, `MakeModule`, `DeleteModule`, `PublishModule`, `DiagnoseModules`, `CheckBoundaries`), one public `handle()` (and `plan()` when the command previews), refusing with a `RuntimeException` whose message is shown as is. Commands only read input, call the action and print.
- `src/Console/<Area>`: one class per command, grouped as in the command reference (Architecture, Database, Development, Discovery, Make, Management, Sources, State). `Make\GeneratorCommand`, `Make\TableMigrationCommand` and `Database\MigrationCommand` are the shared bases.
- `src/Support`: small internal helpers. Everything outside the root and `Contracts`/`Facades` is `@internal`.
- `tests/` mirrors `src/`.

## Package-specific constraints

- The package requires `laravel/framework`, not single `illuminate/*` components: it works only inside a full application and relies on the foundation path helpers, the application provider lifecycle, route and configuration caches, and the Artisan kernel.
- The module manifest `bootstrap/cache/modulator.php` holds absolute paths, like Laravel's configuration cache: build it on the server path that runs it.
- Package modules must not be autoloaded by the package's own `composer.json`: Modulator autoloads the selected source only, and `module:cache` refuses Composer mappings that point at a shadowed copy.
- `module:enable` and `module:disable` edit only the top-level `disabled` list of `config/pharaonic/modulator.php` with `Support\ConfigEditor` (tokenizer, no code execution, no `var_export` rewrite), under a lock and with an atomic rename; a computed list is refused.
- Generators extend no `make:*` command of Laravel: those are bound to the application namespace and change per Laravel version. The stubs must produce code valid on the line's lowest PHP and Laravel; where the output differs between Laravel versions (factories, rules, Markdown mail, anonymous migrations, the cache-locks and failed-jobs uuid columns) the generator picks the stub that matches the running version, so the same code serves every line. `module:make:migration` runs Laravel's `make:migration`.
- `module:make:enum` exists only on lines that require `pharaonic/php-smart-enum`, and every enum stub uses its `SmartEnum` trait (decided 2026-11-21). smart-enum needs PHP 8.1, so the lines that support PHP 8.0 (6.x–9.x) do not ship the command; it starts on 10.x. These and the generators below are the recorded divergences between lines.
- A generator, or a generator option, that needs a newer Laravel than the line's lowest is not shipped on that line, and arrives on the first line whose floor supports it (decided 2026-11-21): `module:make:cast` (with `--inbound`) and `module:make:component` from 7.x; `module:make:queue-batches-table` and `job --batched` from 8.x; `controller --singleton` / `--creatable` from 9.x; `resource --json-api` from the first line whose Laravel floor ships `JsonApiResource`.
- `ModulatorServiceProvider` registers and boots every module's `ModuleResources` itself (from 11.x): Laravel 11 keys `$serviceProviders` by class, so `app->register(new ModuleResources(...))` once per module would keep only the last one. They boot in an app `booting` callback, before every provider, in module order (decided 2026-11-21).
- Module migrations share Laravel's migration table and batches: every database command passes the module's `database/migrations` as `--path`, so it only touches that module's migrations, and no module command may run `migrate:fresh` or `db:wipe`.
- Livewire is optional (`suggest` only, never `require` or `require-dev`): components in `<Module>\Http\Livewire` (`<Module>\Livewire` from 10.x, Livewire 3 and 4) are registered as `<slug>::<kebab path>` only when the `livewire` binding exists, and `module:make:livewire` refuses without Livewire. It starts on 7.x (Livewire 2 needs Laravel 7); the lines that install Livewire 3 (10.x on) move the namespace to `<Module>\Livewire`, as Livewire 3 does. Filament is optional the same way (`suggest` only): from 8.x, the resources, pages and widgets directly in `<Module>\\Filament\\{Resources,Pages,Widgets}` are registered when the `filament` binding exists, the way a Filament 2 plugin registers them (classes on resolving, Livewire components at boot), and `module:make:filament-resource` writes Filament 2 resources; From 10.x (Filament 3) the classes join panels by directory through `Panel::configureUsing()`, which a panel runs when it gets its id, before it registers routes and Livewire components (decided 2026-11-21): `<Module>\Filament\{Resources,Pages,Widgets}` go to the default panel, `<Module>\Filament\<PanelId>\…` to the panel with that StudlyCase id. Their code names them only by class-name strings, so `src` analyses without them (`FilamentSchema`, which uses Filament's generator traits, is excluded from PHPStan); the tests that need them skip unless they are installed, and the missing-package tests run without them. CI's `integrations` job installs every `suggest` package on the runner and runs the tests with them. Vite (from 9.1.0) adds module entry points to the application's single build: `module:vite` prints the enabled modules' existing `pharaonic.modulator.vite.entries` as JSON, `resources/vite/modulator.js` (shipped in the package, no npm package) turns that into `vite.config.js` inputs, and `@moduleVite` renders Laravel's `Vite` tags for one module. It needs no optional package: `Illuminate\Foundation\Vite` is in every Laravel from 9.x, so 6.x–8.x do not have it.
- On 11.x the Filament integration targets Filament 3, which Laravel 11 can install; Composer would pick Filament 5, so the CI `integrations` job pins `filament/filament:^3.3` (with Livewire 3), and runs Livewire 4 on its own. Filament 4/5 (new page and resource API) is the next feature, then ported to every later line (decided 2026-11-21).
- laravel-executor is optional the same way (`suggest` only), from 10.x (its lowest line): an enabled module's `executors` folder is added to the executor pool (`getPool()->addPath()`) when the `pharaonic.executor.manager` binding exists, and `module:make:executor` runs laravel-executor's `make:executor --path=` into it, refusing without the package (decided 2026-11-21).
- From 10.x the package is AI ready: `resources/boost` (guideline and `modulator-development` skill) is written from the 10.x code, kept the same on every later line, and never export-ignored.
- `tests/Feature/ComposerAutoloadTest.php` (fixtures in `tests/Fixtures/Composer`) installs a real application with Composer (network or a warm Composer cache) and runs it under the default, optimized and authoritative autoloaders.

## Branch notes

Fixes land on the oldest affected line and are ported to every later line and `develop`.

## Documentation

https://pharaonic.dev/packages/laravel/modulator

Keep this file factual and package-specific. General Pharaonic engineering policy belongs in the private engineering repository, not here.
