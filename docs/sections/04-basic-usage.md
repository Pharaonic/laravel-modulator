## Basic Usage

Create a module, add a class, and use it. Nothing needs to be registered in `composer.json`.

```bash title="Terminal" no-line-numbers
php artisan module:make Shop
php artisan module:make:model Shop Product --migration
```

```php title="Modules/Shop/app/Models/Product.php"
namespace Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    //
}
```

```php title="Anywhere in the application"
use Modules\Shop\Models\Product;
use Pharaonic\Laravel\Modulator\Facades\Modules;

Product::query()->get();

Modules::find('Shop')->isEnabled(); // true
Modules::find('Shop')->path('routes/web.php'); // ".../Modules/Shop/routes/web.php"
```

### What A Module Loads

| Convention | Loaded as |
| --- | --- |
| `app/Providers/<Name>ServiceProvider.php` | The module service provider (optional; only this one is registered automatically) |
| `config/*.php` | `config('modules.<slug>.<file>')` |
| `resources/views` | `view('<slug>::name')` |
| `lang` or `resources/lang` | `__('<slug>::file.key')`, plus JSON translations |
| `database/migrations` | Run by `php artisan migrate` |
| `database/factories`, `database/seeders` | `Modules\Shop\Database\Factories\`, `Modules\Shop\Database\Seeders\` |
| `routes/web.php` | The `web` middleware group |
| `routes/api.php` | The `api` middleware group and the `api` prefix |
| `routes/console.php`, `app/Console/Commands` | Registered in the console |
| `tests` | `Modules\Shop\Tests\` |

The slug is the identity in kebab case: `Shop` is `shop`, `Commerce/Checkout` is `commerce-checkout`.

:::tip
Routes get no automatic URL prefix: write the URIs you want in the module route files.
:::
