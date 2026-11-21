## Installation

Install the package with Composer; the service provider registers itself.

### Requirements

- PHP 8.3, 8.4 or 8.5
- Laravel 13.0 or newer within 13.x

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
