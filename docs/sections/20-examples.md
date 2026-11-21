## Examples

### 1. A Module With A Route, A View And A Model

- ===Route

  ```php title="Modules/Shop/routes/web.php"
  use Illuminate\Support\Facades\Route;
  use Modules\Shop\Http\Controllers\ProductController;

  Route::get('/shop', [ProductController::class, 'index'])->name('shop.index');
  ```

- ===Controller

  ```php title="Modules/Shop/app/Http/Controllers/ProductController.php"
  namespace Modules\Shop\Http\Controllers;

  use Illuminate\Routing\Controller;
  use Modules\Shop\Models\Product;

  class ProductController extends Controller
  {
      public function index()
      {
          return view('shop::products.index', ['products' => Product::all()]);
      }
  }
  ```

- ===View

  ```blade title="Modules/Shop/resources/views/products/index.blade.php"
  @foreach ($products as $product)
      <p>{{ $product->name }}</p>
  @endforeach
  ```

### 2. Override A Module From A Package

```bash title="Terminal" no-line-numbers
composer require acme/shop
php artisan module:source Shop        # active: acme/shop
php artisan module:publish Shop
php artisan module:source Shop        # active: local, shadowed: acme/shop
```

Change `Modules/Shop` freely; `composer update` never touches it.

### 3. A Public Contract Between Modules

- ===Catalog

  ```php title="Modules/Catalog/module.php"
  return ['exposes' => ['Contracts']];
  ```

- ===Shop

  ```php title="Modules/Shop/module.php"
  return ['dependencies' => ['Catalog']];
  ```

- ===CI

  ```bash title="Terminal" no-line-numbers
  php artisan module:check
  ```

`Shop` may use `Modules\Catalog\Contracts\...`; using `Modules\Catalog\Models\Product` fails the check.

### 4. Switch A Feature Off

```bash title="Terminal" no-line-numbers
php artisan module:disable Analytics
php artisan config:cache
php artisan queue:restart
```

### 5. Deploy With An Authoritative Class Map

```bash title="Deployment" no-line-numbers
composer install --no-dev --classmap-authoritative
php artisan optimize        # caches config, routes and the module manifest
```
