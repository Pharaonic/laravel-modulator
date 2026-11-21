---
view: components.packages.quick-look
title: A quick look
subtitle: A module class, used anywhere, with the registry one call away.
file: routes/web.php
language: php
code: |
  use Modules\Shop\Models\Product;
  use Pharaonic\Laravel\Modulator\Facades\Modules;

  // Modules/Shop/app/Models/Product.php, autoloaded by convention
  Route::get('/products', function () {
      return Product::query()->latest()->get();
  });

  Modules::find('Shop')->isEnabled();      // true
  Modules::find('Shop/Orders')->parent();  // "Shop"
---
