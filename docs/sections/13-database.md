## Database Commands

Each command runs Laravel's own command limited to the module's `database/migrations`.

```bash title="Terminal" no-line-numbers
php artisan module:migrate Shop
php artisan module:migrate:status Shop
php artisan module:migrate:rollback Shop --step=1
php artisan module:migrate:reset Shop
php artisan module:migrate:refresh Shop --seed
php artisan module:seed Shop --class=ProductSeeder
```

| Command | Options |
| --- | --- |
| `module:migrate` | `--database=`, `--force`, `--pretend`, `--seed`, `--step` |
| `module:migrate:rollback` | `--database=`, `--force`, `--pretend`, `--step=` |
| `module:migrate:status` | `--database=` |
| `module:migrate:reset` | `--database=`, `--force`, `--pretend` |
| `module:migrate:refresh` | `--database=`, `--force`, `--seed`, `--step=` |
| `module:seed` | `--class=` (default `DatabaseSeeder`, relative to `Modules\Shop\Database\Seeders`), `--database=`, `--force` |

:::danger module:migrate:fresh is removed
Laravel's `migrate:fresh` drops every table of the database, whatever `--path` says. `module:migrate:fresh` now fails with an explanation. Use `module:migrate:refresh <module>`, or `php artisan migrate:fresh` to rebuild the whole database on purpose.
:::

:::warning Shared migration table
Modules and the application share Laravel's migration table and batches. A rollback reverts only this module's migrations of the last batch (or of the last `--step` migrations), and foreign keys between modules are not followed: roll back dependent modules first.
:::
