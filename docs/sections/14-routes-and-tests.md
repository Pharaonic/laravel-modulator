## Routes And Tests

### Routes

`module:routes` lists the routes a module owns: those loaded from its route files, and those whose controller is in the module namespace (1.x modules). A route name prefix is not ownership.

```bash title="Terminal" no-line-numbers
php artisan module:routes
php artisan module:routes Shop --method=GET
php artisan module:routes Shop --json
php artisan module:routes --compact
php artisan module:routes --columns=uri,middleware --sort=uri --reverse
```

A module includes the routes of its children. Ownership survives `route:cache`.

| Option | Description |
| --- | --- |
| `--method=` | Only the routes answering this method. |
| `--compact`, `-c` | Only the module, method, URI and action columns. |
| `--columns=` | The columns to show: `module`, `domain`, `method`, `uri`, `name`, `action`, `middleware` (repeat it or separate with commas). |
| `--sort=` | The column to sort by; `module` by default. |
| `--reverse`, `-r` | Reverse the order. |
| `--json` | Output JSON, limited to the chosen columns. |

### Tests

`module:test` runs the module's `tests` directory with Pest (when `vendor/bin/pest` exists) or PHPUnit, and returns their exit code.

```bash title="Terminal" no-line-numbers
php artisan module:test Shop
php artisan module:test Shop --filter=CartTest --stop-on-failure
php artisan module:test Shop --list
php artisan module:test Shop --coverage
php artisan module:test Shop --parallel --profile   # Pest only
```

Arguments are passed to the runner as a list, never through a shell. The runner uses the application's `phpunit.xml`, so module tests extend the application's `Tests\TestCase` and get its environment.

### Running Module Tests With The Application's Suite

Laravel's `phpunit.xml` only lists `tests/Unit` and `tests/Feature`, so `vendor/bin/phpunit` alone skips module tests. Add a suite for the local pool; nested modules are included:

```xml title="phpunit.xml"
<testsuite name="Modules">
    <directory suffix="Test.php">./Modules</directory>
</testsuite>
```

Then `vendor/bin/phpunit` runs everything, and `vendor/bin/phpunit --testsuite Modules` runs only module tests.

Pest reads the same `phpunit.xml`, so `vendor/bin/pest --testsuite Modules` works too.

### Pest

The generators write Pest tests when `vendor/bin/pest` exists, unless you pass `--phpunit`. Laravel 7's skeleton pins `nunomaduro/collision` ^4.3, and Pest 1 needs ^5.11, so install them together:

```bash title="Terminal" no-line-numbers
composer require --dev nunomaduro/collision:^5.11 pestphp/pest:^1.23 pestphp/pest-plugin-laravel:^1.4 -W
php artisan pest:install
```

`tests/Pest.php` binds `Tests\TestCase` to `tests/Feature` only, and module tests live in the module. So a module's Pest feature test binds it itself, and the generators write it that way:

```php title="Modules/Shop/tests/Feature/CartTest.php"
<?php

uses(Tests\TestCase::class);

test('the cart page answers', function () {
    $this->get('/cart')->assertOk();
});
```

Unit tests (`--unit`) do not. Do not also bind module folders in `tests/Pest.php`: Pest refuses a test case bound twice.
