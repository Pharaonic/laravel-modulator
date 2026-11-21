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

    public function test_deleting_a_directory_never_follows_links(): void
    {
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modulator-files-'.bin2hex(random_bytes(6));
        $outside = $root.DIRECTORY_SEPARATOR.'outside';
        $module = $root.DIRECTORY_SEPARATOR.'Shop';
        mkdir($outside, 0777, true);
        mkdir($module.DIRECTORY_SEPARATOR.'app', 0777, true);
        file_put_contents($outside.DIRECTORY_SEPARATOR.'keep.txt', 'keep');
        file_put_contents($module.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Cart.php', '<?php');

        if (! @symlink($outside, $module.DIRECTORY_SEPARATOR.'linked')) {
            $this->markTestSkipped('Symlinks cannot be created here.');
        }

        Files::deleteDirectory($module);

        $this->assertDirectoryDoesNotExist($module);
        $this->assertFileExists($outside.DIRECTORY_SEPARATOR.'keep.txt', 'The link target is untouched.');

        $link = $root.DIRECTORY_SEPARATOR.'link-to-outside';
        symlink($outside, $link);
        Files::deleteDirectory($link);

        $this->assertFalse(is_link($link) || file_exists($link));
        $this->assertFileExists($outside.DIRECTORY_SEPARATOR.'keep.txt', 'Deleting a link deletes only the link.');

        Files::deleteDirectory($root);
        $this->assertDirectoryDoesNotExist($root);
    }
}
