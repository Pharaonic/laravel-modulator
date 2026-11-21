## Enabling And Disabling Modules

Every discovered module is enabled unless its identity is in `pharaonic.modulator.disabled`. The commands edit that list in `config/pharaonic/modulator.php` and keep the rest of the file as it is.

```bash title="Terminal" no-line-numbers
php artisan module:disable Shop
php artisan module:enable Commerce/Checkout
php artisan module:status
php artisan module:status Commerce/Checkout
```

### Rules

| Situation | Result |
| --- | --- |
| Disable a parent | Its children are disabled too; the config lists only the parent. |
| Enable a child of a disabled parent | Refused. Add `--with-parents` to enable the parents too. |
| Enable a module whose dependency is disabled | Refused. Add `--with-dependencies`. |
| Disable a module others depend on | Refused, with the list. `--cascade` disables them too (confirm, or add `--force`). |
| Production | Asks for confirmation; `--force` skips it. |

### Configured And Effective State

`module:status` shows both, with the chain of reasons:

```text title="php artisan module:status Shop/Orders" no-copy
Module:     Shop/Orders
Configured: enabled
Effective:  disabled
  Shop/Orders: Disabled because its parent [Shop] is disabled.
  Shop: Disabled in configuration (pharaonic.modulator.disabled).
```

:::warning Computed lists are not edited
When `disabled` is computed (for example `explode(',', env('MODULES_DISABLED'))`), the commands refuse and leave the file untouched. Edit it by hand.
:::

:::info Cached configuration
Changing the file does not change a running application. With `config:cache`, run `php artisan config:cache` again; restart queue workers, and run `php artisan octane:reload` if you use Octane.
:::
