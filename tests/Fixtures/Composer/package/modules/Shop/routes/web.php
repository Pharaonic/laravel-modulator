<?php

use Illuminate\Support\Facades\Route;

Route::get('shop', 'Modules\Shop\Http\Controllers\ShopController@index')->name('shop.index');
