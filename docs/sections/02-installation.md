## Installation

Install the package with Composer; the service provider registers itself.

### Requirements

- PHP 8.0, 8.1 or 8.2
- Laravel 9.42 or newer within 9.x

### Composer Installation

```bash title="Terminal" no-line-numbers
composer require pharaonic/laravel-modulator
```

### Publish Configuration

Publish the configuration when you need to change the pools or the generation settings. `module:enable` and `module:disable` publish it for you the first time they change it.

```bash title="Terminal" no-line-numbers
php artisan vendor:publish --tag=modulator-config
```

The file is written to `config/pharaonic/modulator.php`.

### Create Your First Module

```bash title="Terminal" no-line-numbers
php artisan module:make Shop
```

:::success Installation Complete
You're all set! `Modules/Shop` is a module: add classes under `Modules/Shop/app` and they autoload as `Modules\Shop\...`.
:::
