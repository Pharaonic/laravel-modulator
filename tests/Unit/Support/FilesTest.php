<?php

namespace Pharaonic\Laravel\Modulator\Tests\Unit\Support;

use Pharaonic\Laravel\Modulator\Support\Files;
use PHPUnit\Framework\TestCase;

class FilesTest extends TestCase
{
    public function test_paths_with_mixed_separators_compare_as_the_same_path(): void
    {
        $base = DIRECTORY_SEPARATOR.'app';

        $this->assertTrue(Files::within($base.'/Modules\\Shop/app\\Models/Product.php', $base.'\\Modules/Shop'));
        $this->assertTrue(Files::within($base.'/Modules/Shop/', $base.'\\Modules\\Shop'));
        $this->assertFalse(Files::within($base.'/Modules/ShopExtra/app', $base.'/Modules/Shop'));
        $this->assertFalse(Files::within($base.'/Modules', $base.'/Modules/Shop'));
    }

    public function test_relative_paths_are_shown_with_forward_slashes(): void
    {
        $base = DIRECTORY_SEPARATOR.'app';

        $this->assertSame('Modules/Shop/app/Cart.php', Files::relative($base.'\\Modules\\Shop/app\\Cart.php', $base.'/'));
        $this->assertSame(Files::normalize('/elsewhere/Shop'), Files::relative('/elsewhere/Shop', $base));
        $this->assertSame(Files::normalize($base), Files::relative($base, $base));
    }

    public function test_normalize_uses_the_platform_separator_without_a_trailing_one(): void
    {
        $this->assertSame(str_replace('/', DIRECTORY_SEPARATOR, '/app/Modules/Shop'), Files::normalize('/app\\Modules/Shop/'));
        $this->assertSame(DIRECTORY_SEPARATOR === '/' ? '/' : '\\', Files::normalize(DIRECTORY_SEPARATOR));
    }
}
