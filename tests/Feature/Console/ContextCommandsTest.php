<?php

namespace Pharaonic\Laravel\Modulator\Tests\Feature\Console;

use Pharaonic\Laravel\Modulator\Tests\TestCase;

class ContextCommandsTest extends TestCase
{
    /**
     * @var string|false
     */
    private $ci;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ci = getenv('CI');
        putenv('CI');

        $this->directory('Modules/Shop/app');
        $this->directory('Modules/Shop/Modules/Products/app');
        $this->directory('Modules/Blog/app');
        $this->reload();
    }

    protected function tearDown(): void
    {
        putenv($this->ci === false ? 'CI' : 'CI='.$this->ci);

        parent::tearDown();
    }

    public function test_the_selection_persists_and_is_used_when_the_module_is_left_out(): void
    {
        $this->assertSame(0, $this->command('module:use', ['module' => 'Shop/Products'])[0]);

        $this->reload();

        [$status, $output] = $this->command('module:current');
        $this->assertSame(0, $status);
        $this->assertStringContainsString('Shop/Products', $output);

        [$status, $output] = $this->command('module:make:model', ['module' => 'Product'], true);
        $this->assertSame(0, $status, $output);
        $this->assertStringContainsString('selected with module:use', $output);
        $this->assertFileExists($this->base.'/Modules/Shop/Modules/Products/app/Models/Product.php');
    }

    public function test_an_explicit_module_wins_and_does_not_change_the_selection(): void
    {
        $this->command('module:use', ['module' => 'Shop']);

        [$status] = $this->command('module:make:model', ['module' => 'Blog', 'name' => 'Post'], true);

        $this->assertSame(0, $status);
        $this->assertFileExists($this->base.'/Modules/Blog/app/Models/Post.php');
        $this->assertStringContainsString('Shop', $this->command('module:current')[1]);
    }

    public function test_non_interactive_consoles_and_ci_ignore_the_selection(): void
    {
        $this->command('module:use', ['module' => 'Shop']);

        [$status, $output] = $this->command('module:make:model', ['module' => 'Product']);
        $this->assertSame(1, $status);
        $this->assertStringContainsString('Give the module identity', $output);

        putenv('CI=true');
        [$status] = $this->command('module:make:model', ['module' => 'Product'], true);
        $this->assertSame(1, $status);

        $this->assertFileDoesNotExist($this->base.'/Modules/Shop/app/Models/Product.php');
    }

    public function test_a_selection_that_no_longer_exists_is_reported(): void
    {
        $this->command('module:use', ['module' => 'Blog']);
        (new \Illuminate\Filesystem\Filesystem)->deleteDirectory($this->base.'/Modules/Blog');
        $this->reload();

        [$status, $output] = $this->command('module:current');
        $this->assertSame(1, $status);
        $this->assertStringContainsString('no longer exists', $output);

        [$status, $output] = $this->command('module:make:model', ['module' => 'Post'], true);
        $this->assertSame(1, $status);
        $this->assertStringContainsString('module:use --clear', $output);

        $this->command('module:use', ['--clear' => true]);
        $this->assertStringContainsString('No module is selected', $this->command('module:current')[1]);
    }

    public function test_use_rejects_unknown_modules(): void
    {
        $this->assertSame(1, $this->command('module:use', ['module' => 'Missing'])[0]);
        $this->assertSame(1, $this->command('module:use')[0]);
    }
}
