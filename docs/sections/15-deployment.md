## Deployment And Autoloading

### The Module Manifest

Discovery is cached in `bootstrap/cache/modulator.php`: pools, sources, a class map per module and the diagnostics. In development it is rebuilt when the pools, the installed packages or the module directories change. In production, build it once per deployment.

```bash title="Deployment" no-line-numbers
composer install --no-dev --optimize-autoloader   # or --classmap-authoritative
php artisan module:cache
php artisan config:cache
php artisan route:cache
php artisan queue:restart
```

`php artisan optimize` also runs `module:cache`, and `optimize:clear` runs `module:clear`. `module:cache` fails, and writes nothing, when discovery finds an error.

### Composer Modes

| Composer mode | Module classes |
| --- | --- |
| Default | Found through the module class map and PSR-4 prefixes; new classes work at once. |
| `--optimize` | Same. |
| `--classmap-authoritative` | Only classes in a class map load. Run `module:cache` after adding module classes. |

The manifest holds absolute paths, like Laravel's configuration cache: build it on the server path that runs it.

### Long-Running Workers

Modules are resolved once, when the application boots: discovery, the module class maps, providers, routes, config and `helpers.php`. A process that keeps the application booted keeps that state until it restarts. After publishing, deleting, enabling or disabling a module, run `php artisan queue:restart`, and rebuild route and config caches if you use them.

### Octane

Octane needs Laravel 8 or later. The module state is read-only after boot and holds no request data, so it is safe to share between requests. Two things to know:

- After publishing, deleting, enabling or disabling a module, run `php artisan octane:reload`. The commands remind you when Octane is installed.
- `octane:start --watch` does not watch the local pool. Add it to `watch` in `config/octane.php`:

```php title="config/octane.php"
'watch' => [
    'app',
    'bootstrap',
    'config/**/*.php',
    'database/**/*.php',
    'Modules',
    // ...
],
```

The 1.x pool (`app/Modules`) is already watched through `app`. A package pool lives in `vendor/` and changes with `composer.lock`, which Octane watches.

### Commands

| Command | Description |
| --- | --- |
| `module:discover` | Discover again, rebuild the manifest and list the modules. |
| `module:cache` | Rebuild the manifest; fails on any error. |
| `module:clear` | Delete the manifest. |
