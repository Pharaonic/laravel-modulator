## Current Module

Select a module for your console, then leave the module out of module commands.

```bash title="Terminal" no-line-numbers
php artisan module:use Shop/Products
php artisan module:make:model Product          # Shop/Products
php artisan module:make:model Blog Post         # the explicit module wins
php artisan module:current
php artisan module:use --clear
```

Resolution order: the explicit argument, then the selected module, then an interactive choice; otherwise the command fails and asks for the identity.

- The selection is stored per operating-system user in `storage/framework/modulator/context`, kept out of version control.
- It is used only on interactive consoles, never when `CI` is set, in production, or with `--no-interaction`. Scripts and deployments must give the module explicitly.
- An explicit module never changes the selection.
- A selected module that no longer exists is reported.
