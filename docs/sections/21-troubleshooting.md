## Troubleshooting

### A module class is not found in production

Composer's class map is authoritative and the class was added after the last `module:cache`. Run `php artisan module:cache` (or `optimize`) during deployment. `module:doctor` reports this case.

### module:cache fails with "Composer maps [...]"

A package autoloads its own module namespace in its `composer.json`, so Composer would load a shadowed copy. Remove that `autoload` entry from the package; Modulator autoloads package modules itself.

### "is provided with the same priority by pools [...]"

Two pools hold the same identity with the same priority. Change one pool's priority or remove one copy.

### module:enable says the module cannot be enabled

Its parent or a dependency is disabled; the command prints the chain. Use `--with-parents` or `--with-dependencies`, or enable the blocking module first.

### The disabled list "cannot be edited safely"

`disabled` is computed (env(), explode(), a variable). Edit `config/pharaonic/modulator.php` by hand, or make the list literal.

### The state did not change after module:disable

The configuration is cached or a worker is still running. Run `php artisan config:cache`, then restart queue workers, and run `php artisan octane:reload` if you use Octane.

### module:make:model Product says "Give the module identity"

The selected module is used only on interactive consoles outside CI and production. Pass the module: `module:make:model Shop Product`.

### A second provider in app/Providers does nothing

Only `<Name>ServiceProvider` is registered automatically. Register the other provider from it with `$this->app->register(...)`.
