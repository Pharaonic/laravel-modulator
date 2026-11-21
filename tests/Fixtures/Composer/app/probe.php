<?php

// Boots the fixture application like an HTTP request and reports what it resolved.

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request = Illuminate\Http\Request::create('/shop', 'GET'));

$modules = $app->make(Pharaonic\Laravel\Modulator\ModuleRegistry::class);
$route = $app->make('router')->getRoutes()->getByName('shop.index');

echo json_encode([
    'status' => $response->getStatusCode(),
    'response' => trim((string) $response->getContent()),
    'product' => class_exists('Modules\\Shop\\Models\\Product') ? Modules\Shop\Models\Product::origin() : null,
    'product_file' => class_exists('Modules\\Shop\\Models\\Product') ? (new ReflectionClass('Modules\\Shop\\Models\\Product'))->getFileName() : null,
    'order' => class_exists('Modules\\Shop\\Orders\\Order') ? Modules\Shop\Orders\Order::origin() : null,
    'coupon' => class_exists('Modules\\Shop\\Models\\Coupon'),
    'provider' => $app->bound('shop.provider') ? $app->make('shop.provider') : null,
    'shop_pool' => optional($modules->find('Shop'))->pool(),
    'orders_pool' => optional($modules->find('Shop/Orders'))->pool(),
    'route_module' => $route ? $route->getAction('module') : null,
    'authoritative' => (function () {
        foreach (Composer\Autoload\ClassLoader::getRegisteredLoaders() as $loader) {
            return $loader->isClassMapAuthoritative();
        }
    })(),
]);
