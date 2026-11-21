## Diagnostics

### module:doctor

Checks source collisions, discovery and cache state, statuses, dependencies, providers, autoloading and deployment, and exits with 1 when it finds an error.

```bash title="Terminal" no-line-numbers
php artisan module:doctor
```

It reports, among others: equal-priority sources, invalid identities, missing or cyclic dependencies, a cached manifest that is out of date (an error when Composer's class map is authoritative), providers in `app/Providers` that are not registered automatically, a conventional provider file that does not declare its class, Composer mappings to shadowed copies, discovery caching off in production, and a config file changed after `config:cache`.

### module:graph

Shows the hierarchy and dependencies.

```bash title="Terminal" no-line-numbers
php artisan module:graph
php artisan module:graph --format=json
```

```text title="php artisan module:graph" no-copy
Catalog [enabled]
Shop [enabled] -> needs Catalog
└─ Shop/Orders [enabled]
```

The JSON output lists `modules` and `edges` (`parent` and `dependency` edges, with `missing` for unknown dependencies), ready for CI.

### module:check

A static check of the class references between modules. It reads each module's PHP files (not its tests or child modules) and enforces:

1. a module uses another module only when it declares it in `dependencies`, or when it is one of its ancestors;
2. a parent does not use its own child modules;
3. when a module declares `exposes`, others use only classes in those namespaces.

```bash title="Terminal" no-line-numbers
php artisan module:check
php artisan module:check Shop
php artisan module:check --json
```

:::info Limits
Imports, fully qualified and qualified class names are checked. Names built at runtime (strings, container keys, dynamic calls) are not seen.
:::
