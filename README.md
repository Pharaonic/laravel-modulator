<p align="center"><a href="https://pharaonic.dev/packages/laravel/modulator" target="_blank"><img src="https://raw.githubusercontent.com/Pharaonic/laravel-modulator/develop/docs/cover.webp"></a></p>

<p align="center">
  <a href="https://php.net" target="_blank"><img src="https://img.shields.io/static/v1?label=PHP&message=8.3%20|%208.4%20|%208.5&color=blue&style=flat-square" alt="PHP Version : 8.3 | 8.4 | 8.5"></a>
  <a href="https://laravel.com" target="_blank"><img src="https://img.shields.io/static/v1?label=Laravel&message=13.x&color=F05340&style=flat-square" alt="Laravel Version : 13.x"></a>
  <img src="https://img.shields.io/static/v1?label=License&message=MIT&color=brightgreen&style=flat-square" alt="License">
  <img src="https://github.com/Pharaonic/laravel-modulator/actions/workflows/build.yml/badge.svg?branch=13.x" alt="Build">
  <br>
  <a href="https://packagist.org/packages/Pharaonic/laravel-modulator" target="_blank"><img src="https://img.shields.io/static/v1?label=Packagist&message=pharaonic/laravel-modulator&color=blue&logo=packagist&logoColor=white" alt="Source"></a>
  <a href="https://packagist.org/packages/pharaonic/laravel-modulator" target="_blank"><img src="https://poser.pugx.org/pharaonic/laravel-modulator/v" alt="Packagist Version"></a>
  <a href="https://packagist.org/packages/pharaonic/laravel-modulator" target="_blank"><img src="https://poser.pugx.org/pharaonic/laravel-modulator/downloads" alt="Packagist Downloads"></a>
  <a href="https://pharaonic.dev/packages/laravel/modulator#ai-ready" target="_blank"><img src="https://img.shields.io/static/v1?label=AI%20Ready&message=Laravel%20Boost&color=8B5CF6&style=flat-square" alt="AI Ready: Laravel Boost"></a>
</p>

<h3 align="center">Laravel-native modules: discovered by convention, nested, overridable from packages, and autoloaded in every Composer mode.</h3>
<br>

## Installation

```bash
composer require pharaonic/laravel-modulator
```

The service provider is registered automatically. Publish the configuration when you need to change it:

```bash
php artisan vendor:publish --tag=modulator-config
```

## Usage

A directory with `app/` under `Modules/` is a module. No `composer.json` or manifest is needed:

```bash
php artisan module:make Shop
php artisan module:make:model Shop Product --migration
```

```php
// Modules/Shop/app/Models/Product.php
namespace Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;

final class Product extends Model {}

// Anywhere in the application
Modules\Shop\Models\Product::query()->get();
Pharaonic\Laravel\Modulator\Facades\Modules::find('Shop')->isEnabled();
```

On deployment, after `composer install`, run `php artisan module:cache`.

## Documentation

Full documentation: [Laravel Modulator Documentation](https://pharaonic.dev/packages/laravel/modulator).

## Support & Security

See [SUPPORT.md](SUPPORT.md) and [SECURITY.md](SECURITY.md). Contributions are welcome; see [CONTRIBUTING.md](CONTRIBUTING.md).

## License

MIT. See [LICENSE](LICENSE).
