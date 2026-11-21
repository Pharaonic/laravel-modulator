<?php

namespace Pharaonic\Laravel\Modulator\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Pharaonic\Laravel\Modulator\Support\Files;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * A real application installed with Composer: it requires this package and a
 * fixture package (acme/shop) whose composer.json declares a module pool. The
 * same module class is resolved under the default, optimized (-o) and
 * authoritative (-a) Composer autoloaders, before and after a local override,
 * with route and configuration caches, following the documented build steps:
 *
 *   composer install / dump-autoload [-o|-a]  (runs package:discover)
 *   php artisan module:cache
 */
#[Group('composer')]
class ComposerAutoloadTest extends TestCase
{
    /**
     * @var string|null
     */
    private static $root;

    /**
     * @var string
     */
    private static $composer;

    public static function setUpBeforeClass(): void
    {
        $composer = (new ExecutableFinder)->find('composer');

        if ($composer === null) {
            return;
        }

        self::$composer = $composer;
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modulator-composer-'.bin2hex(random_bytes(6));
        $files = new Filesystem;
        $files->copyDirectory(__DIR__.'/../../vendor/orchestra/testbench-core/laravel', $root.'/app');
        $files->copyDirectory(__DIR__.'/../Fixtures/Composer/app', $root.'/app');
        $files->copyDirectory(__DIR__.'/../Fixtures/Composer/package', $root.'/package');
        $files->delete($root.'/app/composer.json');

        $config = (string) file_get_contents($root.'/app/config/app.php');
        $config = str_replace("env('APP_KEY')", "'base64:".base64_encode(str_repeat('k', 32))."'", $config);
        $config = str_replace("(bool) env('APP_DEBUG', false)", 'true', $config);
        $config = str_replace('// App\\Providers\\RouteServiceProvider::class,', 'App\\Providers\\RouteServiceProvider::class,', $config);
        // Laravel 11's configuration lists no route provider to uncomment: add it.
        $config = str_replace('// App\\Providers\\AppServiceProvider::class,', 'App\\Providers\\RouteServiceProvider::class,', $config);
        file_put_contents($root.'/app/config/app.php', $config);

        file_put_contents($root.'/app/composer.json', (string) json_encode([
            'name' => 'fixture/app',
            'type' => 'project',
            // The versions this test run installed (lowest or highest), so the
            // application runs on the same framework and components.
            'require' => array_merge(self::installedVersions(), [
                'acme/shop' => '*@dev',
                'pharaonic/laravel-modulator' => '*@dev',
            ]),
            'repositories' => [
                ['type' => 'path', 'url' => realpath(__DIR__.'/../..'), 'options' => ['symlink' => true]],
                ['type' => 'path', 'url' => '../package', 'options' => ['symlink' => true]],
            ],
            'autoload' => ['psr-4' => ['App\\' => 'app/']],
            'scripts' => ['post-autoload-dump' => ['@php artisan package:discover --ansi']],
            'config' => [
                'audit' => ['block-insecure' => false],
                'policy' => ['advisories' => ['block' => false]],
                'sort-packages' => true,
            ],
            'minimum-stability' => 'dev',
            'prefer-stable' => true,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        self::$root = $root;
        self::composer(['install', '--no-interaction', '--no-progress']);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$root !== null) {
            // The path repositories are links (junctions on Windows) to this
            // repository and to the fixture package: never follow them.
            Files::deleteDirectory(self::$root);
            self::$root = null;
        }
    }

    protected function setUp(): void
    {
        if (self::$root === null) {
            $this->markTestSkipped('Composer is not available on PATH.');
        }

        $this->reset();
    }

    /**
     * @return array<string, array{list<string>, bool}>
     */
    public static function modes(): array
    {
        return [
            'default' => [[], false],
            'optimized' => [['--optimize'], false],
            'authoritative' => [['--classmap-authoritative'], true],
        ];
    }

    /**
     * @param  list<string>  $flags
     */
    #[DataProvider('modes')]
    public function test_a_package_module_then_its_local_override_under_every_autoloader(array $flags, bool $authoritative): void
    {
        self::composer(array_merge(['dump-autoload', '--no-interaction'], $flags));
        $this->artisan(['module:cache']);

        $probe = $this->probe();
        $this->assertSame($authoritative, $probe['authoritative']);
        $this->assertSame(200, $probe['status'], (string) $probe['response']);
        $this->assertSame('shop:package:package view', $probe['response']);
        $this->assertSame('package', $probe['product']);
        $this->assertSame('package', $probe['provider']);
        $this->assertSame('acme/shop', $probe['shop_pool']);
        $this->assertSame('Shop', $probe['route_module']);

        // Publish, then customise the local copy: class, view and provider.
        $this->artisan(['module:publish', 'Shop']);
        $this->customise('Modules/Shop');
        $this->artisan(['module:cache']);

        $probe = $this->probe();
        $this->assertSame('shop:local:local view', $probe['response']);
        $this->assertSame('local', $probe['product']);
        $this->assertSame(realpath(self::$root.'/app/Modules/Shop/app/Models/Product.php'), realpath((string) $probe['product_file']));
        $this->assertSame('local', $probe['provider']);
        $this->assertSame('local', $probe['shop_pool']);
        $this->assertSame('package', $probe['order'], 'The child module still comes from the package.');
        $this->assertSame('acme/shop', $probe['orders_pool']);

        // Route and configuration caches keep the override.
        $this->artisan(['route:cache']);
        $this->artisan(['config:cache']);

        $probe = $this->probe();
        $this->assertSame('shop:local:local view', $probe['response']);
        $this->assertSame('Shop', $probe['route_module']);
        $this->assertSame('local', $probe['provider']);

        $this->artisan(['route:clear']);
        $this->artisan(['config:clear']);

        // A new class: PSR-4 finds it at once, an authoritative class map only after module:cache.
        file_put_contents(self::$root.'/app/Modules/Shop/app/Models/Coupon.php', "<?php\n\nnamespace Modules\\Shop\\Models;\n\nfinal class Coupon\n{\n}\n");
        $this->assertSame(! $authoritative, $this->probe()['coupon']);
        $this->artisan(['module:cache']);
        $this->assertTrue($this->probe()['coupon']);

        // Removing the override restores the package after a rebuild.
        (new Filesystem)->deleteDirectory(self::$root.'/app/Modules/Shop');
        $this->artisan(['module:cache']);

        $probe = $this->probe();
        $this->assertSame('shop:package:package view', $probe['response']);
        $this->assertSame('acme/shop', $probe['shop_pool']);
    }

    /**
     * @param  list<string>  $flags
     */
    #[DataProvider('modes')]
    public function test_a_child_module_overridden_alone_under_every_autoloader(array $flags): void
    {
        self::composer(array_merge(['dump-autoload', '--no-interaction'], $flags));

        $this->artisan(['module:publish', 'Shop/Orders']);
        $order = self::$root.'/app/Modules/Shop/Modules/Orders/app/Order.php';
        file_put_contents($order, str_replace("'package'", "'local'", (string) file_get_contents($order)));
        $this->artisan(['module:cache']);

        $probe = $this->probe();
        $this->assertSame('local', $probe['orders_pool']);
        $this->assertSame('local', $probe['order']);
        $this->assertSame('acme/shop', $probe['shop_pool']);
        $this->assertSame('package', $probe['product']);
        $this->assertSame('shop:package:package view', $probe['response']);
    }

    public function test_a_package_that_autoloads_its_module_itself_is_refused(): void
    {
        $package = self::$root.'/package/composer.json';
        $original = (string) file_get_contents($package);
        $manifest = json_decode($original, true);
        $manifest['autoload'] = ['psr-4' => ['Modules\\Shop\\' => 'modules/Shop/app']];
        file_put_contents($package, (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        try {
            self::composer(['update', 'acme/shop', '--no-interaction', '--no-progress']);
            self::composer(['dump-autoload', '--optimize', '--no-interaction']);
            $this->artisan(['module:publish', 'Shop']);

            $process = $this->runArtisan(['module:cache']);

            $this->assertSame(1, $process->getExitCode());
            $this->assertStringContainsString('Composer maps [Modules\\Shop\\Models\\Product]', $process->getOutput());
            $this->assertStringContainsString('Module cache not written', $process->getOutput());
        } finally {
            file_put_contents($package, $original);
            self::composer(['update', 'acme/shop', '--no-interaction', '--no-progress']);
        }
    }

    public function test_the_cli_context_and_route_ownership_across_processes(): void
    {
        self::composer(['dump-autoload', '--no-interaction']);
        $this->artisan(['module:publish', 'Shop']);
        $this->artisan(['module:use', 'Shop']);

        // Another process, on an interactive developer console.
        $process = $this->runArtisan(['module:make:service', 'Pricing'], ['SHELL_INTERACTIVE' => '1', 'CI' => false], false);
        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        $this->assertFileExists(self::$root.'/app/Modules/Shop/app/Services/Pricing.php');

        // CI never trusts the stored selection.
        $process = $this->runArtisan(['module:make:service', 'Refunds'], ['SHELL_INTERACTIVE' => '1', 'CI' => 'true'], false);
        $this->assertSame(1, $process->getExitCode());
        $this->assertFileDoesNotExist(self::$root.'/app/Modules/Shop/app/Services/Refunds.php');

        // Route ownership survives route:cache.
        $this->artisan(['route:cache']);
        $process = $this->runArtisan(['module:routes', 'Shop', '--json']);
        $this->artisan(['route:clear']);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertContains('shop', array_column((array) json_decode($process->getOutput(), true), 'uri'));
    }

    private function customise(string $module): void
    {
        $base = self::$root.'/app/'.$module;

        foreach (['app/Models/Product.php', 'app/Providers/ShopServiceProvider.php'] as $file) {
            file_put_contents($base.'/'.$file, str_replace("'package'", "'local'", (string) file_get_contents($base.'/'.$file)));
        }

        file_put_contents($base.'/resources/views/hello.blade.php', 'local view');
    }

    private function reset(): void
    {
        $files = new Filesystem;
        $files->deleteDirectory(self::$root.'/app/Modules');

        foreach (['modulator.php', 'config.php', 'routes.php'] as $cache) {
            $files->delete(self::$root.'/app/bootstrap/cache/'.$cache);
        }

        $files->deleteDirectory(self::$root.'/app/storage/framework/modulator');
        $files->cleanDirectory(self::$root.'/app/storage/framework/views');
    }

    /**
     * @return array<string, mixed>
     */
    private function probe(): array
    {
        $process = new Process([PHP_BINARY, 'probe.php'], self::$root.'/app');
        $process->mustRun();

        $result = json_decode($process->getOutput(), true);
        $this->assertIsArray($result, $process->getOutput().$process->getErrorOutput());

        return $result;
    }

    /**
     * @param  list<string>  $arguments
     */
    private function artisan(array $arguments): void
    {
        $process = $this->runArtisan($arguments);

        $this->assertSame(0, $process->getExitCode(), 'artisan '.implode(' ', $arguments).":\n".$process->getOutput().$process->getErrorOutput());
    }

    /**
     * @param  list<string>  $arguments
     * @param  array<string, string|false>  $env
     */
    private function runArtisan(array $arguments, array $env = [], bool $noInteraction = true): Process
    {
        $process = new Process(array_merge([PHP_BINARY, 'artisan'], $arguments, $noInteraction ? ['--no-interaction'] : []), self::$root.'/app', $env === [] ? null : $env);
        $process->run();

        return $process;
    }

    /**
     * @param  list<string>  $arguments
     */
    private static function composer(array $arguments): void
    {
        // On Windows, "composer" is a composer.bat wrapper: run it directly, not through PHP.
        $command = preg_match('/\\.(bat|cmd|exe)$/i', self::$composer) === 1 ? [self::$composer] : [PHP_BINARY, self::$composer];
        $process = new Process(array_merge($command, $arguments), self::$root.'/app', ['COMPOSER_NO_INTERACTION' => '1']);
        $process->setTimeout(600);
        $process->mustRun();
    }

    /**
     * The exact versions of the packages this repository requires to run
     * (not its development tools), as installed.
     *
     * @return array<string, string>
     */
    private static function installedVersions(): array
    {
        $installed = json_decode((string) file_get_contents(__DIR__.'/../../vendor/composer/installed.json'), true);
        $dev = $installed['dev-package-names'] ?? [];
        $versions = [];

        foreach ($installed['packages'] ?? $installed as $entry) {
            $name = $entry['name'] ?? null;

            if (is_string($name) && ! in_array($name, $dev, true)) {
                $versions[$name] = (string) $entry['version'];
            }
        }

        ksort($versions);

        return $versions;
    }
}
