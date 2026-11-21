## Routes And Tests

### Routes

`module:routes` lists the routes a module owns: those loaded from its route files, and those whose controller is in the module namespace (1.x modules). A route name prefix is not ownership.

```bash title="Terminal" no-line-numbers
php artisan module:routes
php artisan module:routes Shop --method=GET
php artisan module:routes Shop --json
```

A module includes the routes of its children. Ownership survives `route:cache`.

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

:::note
Pest cannot be installed with Laravel 6: every Pest release needs a newer `nunomaduro/collision` than Laravel 6 allows. On this line, tests are PHPUnit tests, and `--pest` refuses to write a Pest file.
:::
