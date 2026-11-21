## Publishing Package Modules

`module:publish` copies a package module into the generation pool. The local copy keeps the identity and the namespace, and overrides the package copy.

```bash title="Terminal" no-line-numbers
php artisan module:publish Shop --dry-run
php artisan module:publish Shop
php artisan module:publish Shop/Orders          # one child only
php artisan module:publish Shop --with-children
php artisan module:publish Shop --force         # replace a local copy that differs
```

- The package in `vendor/` is only read.
- The copy is assembled in `storage/framework/modulator/tmp`, then moved into place.
- When a local copy exists and differs, the command lists the added, changed and removed files and stops; `--force` replaces it and keeps the old copy in `storage/framework/modulator/backups`.
- Child modules are published only with `--with-children`; local children stay where they are.

To go back to the package copy, delete the local copy and rebuild the manifest:

```bash title="Terminal" no-line-numbers
php artisan module:delete Shop --force
```

:::warning
Restart queue workers (and run `php artisan octane:reload` with Octane) after publishing, and rebuild route and config caches if you use them.
:::
