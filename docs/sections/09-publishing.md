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

### Keeping A Copy Up To Date

Every published copy gets a `module.lock` next to its `app/`: the package and version it came from, and the hash of each copied file. Commit it with the copy. It is written by `module:publish` only; do not edit it.

```json title="Modules/Shop/Modules/Orders/module.lock"
{
    "module": "Shop/Orders",
    "package": "acme/shop",
    "version": "1.0.0",
    "reference": "4f2c1e0",
    "children": false,
    "files": {
        "app/Models/Order.php": "9a8b…",
        "routes/web.php": "1c2d…"
    }
}
```

After a `composer update`, `module:doctor` compares the copy, its lock and the package:

- files you changed and the package did not are fine: that is what the copy is for;
- files the package changed since the copy are reported, with both versions;
- files changed on both sides are reported separately: merge those by hand.

```text title="php artisan module:doctor" no-copy
[Shop/Orders] was published from acme/shop 1.0.0, and the package (now 2.0.0) changed since: app/Models/Order.php. Changed in both the package and your copy: app/Models/Order.php. ...
```

Bring the package changes into your copy, or take the package version with `module:publish Shop/Orders --force` (your copy is kept as a backup). Publishing again rewrites the lock. `module:source` shows where a copy came from.

To go back to the package copy, delete the local copy and rebuild the manifest:

```bash title="Terminal" no-line-numbers
php artisan module:delete Shop --force
```

:::warning
Restart queue workers (and run `php artisan octane:reload` with Octane) after publishing, and rebuild route and config caches if you use them.
:::
