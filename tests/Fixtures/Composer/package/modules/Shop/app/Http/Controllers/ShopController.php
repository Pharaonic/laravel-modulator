<?php

namespace Modules\Shop\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Shop\Models\Product;

final class ShopController extends Controller
{
    public function index(): string
    {
        return 'shop:'.Product::origin().':'.trim(view('shop::hello')->render());
    }
}
