<?php

namespace Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;

final class Product extends Model
{
    public static function origin(): string
    {
        return 'package';
    }
}
