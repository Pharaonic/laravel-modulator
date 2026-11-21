## Nested Modules

A child module lives in its parent's `Modules/` folder. A folder named `Modules` never adds an identity segment, so `Commerce/Checkout` may live at `Commerce/Checkout` (when `Commerce` is a group) or at `Commerce/Modules/Checkout` (when `Commerce` is a module).

```bash title="Terminal" no-line-numbers
php artisan module:make Commerce
php artisan module:make Commerce/Checkout
# creates Modules/Commerce/Modules/Checkout
```

### Hierarchy Is Not A Dependency

- A disabled parent disables its children. Their own configured state is kept, so they come back with the parent.
- A child is not enabled when its parent is disabled, even if the child itself is enabled in the configuration.
- A parent is not a business dependency: declare the modules a child uses in its `module.php`.
- A child may come from another pool than its parent, e.g. a local `Commerce/Checkout` under a package `Commerce`.

### Reserved Child Names

`Database` and `Tests` cannot be children of a module, because the parent maps `Modules\Commerce\Database\` and `Modules\Commerce\Tests\` itself. A child also cannot share a namespace with a folder of its parent's `app/` (for example `Commerce/Models` next to `Commerce/app/Models`).
