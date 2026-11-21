## Modulator (pharaonic/laravel-modulator)

Laravel-native modules: every directory under `Modules/` is a module, discovered by convention, with nested submodules and package-provided modules. Use the `modulator-development` skill for the full API.

### Conventions

- A module is `Modules/<Name>` with an `app/` directory; its namespace is `Modules\<Name>`, and `app/` never appears in it (`Modules/Shop/app/Models/Product.php` is `Modules\Shop\Models\Product`).
- Identities are StudlyCase paths: `Shop`, `Commerce/Checkout` (a submodule lives in `Modules/Commerce/Modules/Checkout`). The slug is kebab case: `shop`, `commerce-checkout`.
- Module resources are namespaced by slug: `view('shop::index')`, `__('shop::messages.key')`, `config('modules.shop.billing')`.
- Create modules and classes with the `module:*` commands, never by hand-registering anything in `composer.json`.
- A module from a package (`vendor/`) is read-only: `php artisan module:publish Shop` copies it to `Modules/` to change it.

### Main features

- Generators: a `module:make:*` command for every `make:*` command of Laravel, taking `{module} {name}`.

@verbatim
<code-snippet name="Create a module and its classes" lang="bash">
php artisan module:make Shop
php artisan module:make:model Shop Product --migration --factory
php artisan module:make:controller Shop ProductController --resource --model=Product
php artisan module:migrate Shop
</code-snippet>
@endverbatim

- State: modules are enabled unless listed in `pharaonic.modulator.disabled`; change it with `module:enable` / `module:disable`.

@verbatim
<code-snippet name="Find a module" lang="php">
use Pharaonic\Laravel\Modulator\Facades\Modules;

$shop = Modules::find('Shop');
$shop->isEnabled();
$shop->path('routes/web.php');
</code-snippet>
@endverbatim

- Integrations, each only when its package is installed: Livewire 3 or 4 components in `app/Livewire` (`<livewire:shop::cart />`), Filament 3 classes in `app/Filament` (default panel) or `app/Filament/<PanelId>`, laravel-executor executors in `executors/`, and Vite entries through `module:vite` and `@moduleVite('Shop')`.
