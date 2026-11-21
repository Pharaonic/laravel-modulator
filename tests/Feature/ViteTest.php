<?php

namespace Pharaonic\Laravel\Modulator\Tests\Feature;

use InvalidArgumentException;
use Pharaonic\Laravel\Modulator\Tests\TestCase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class ViteTest extends TestCase
{
    /**
     * Shop (CSS and JS), its child Shop/Orders (JS), Blog (CSS) and Admin (no entries).
     */
    private function modules(array $modulator = []): void
    {
        foreach (['Shop', 'Shop/Modules/Orders', 'Blog'] as $module) {
            $this->directory('Modules/'.$module.'/app');
        }

        $this->write('Modules/Shop/resources/css/app.css', 'body {}');
        $this->write('Modules/Shop/resources/js/app.js', '');
        $this->write('Modules/Shop/resources/js/admin.js', '');
        $this->write('Modules/Shop/Modules/Orders/resources/js/app.js', '');
        $this->write('Modules/Blog/resources/css/app.css', '');
        $this->directory('Modules/Admin/app');
        $this->reload($modulator);
    }

    /**
     * @return list<string>
     */
    private function entries(array $parameters = []): array
    {
        [$status, $output] = $this->command('module:vite', $parameters);
        $this->assertSame(0, $status, $output);

        return json_decode($output, true);
    }

    private function render(string $blade): string
    {
        $file = $this->write('resources/views/vite-probe-'.md5($blade).'.blade.php', $blade);

        return view()->file($file)->render();
    }

    public function test_the_command_lists_the_existing_entries_of_the_enabled_modules(): void
    {
        $this->modules();

        $entries = $this->entries();
        sort($entries);

        $this->assertSame([
            'Modules/Blog/resources/css/app.css',
            'Modules/Shop/Modules/Orders/resources/js/app.js',
            'Modules/Shop/resources/css/app.css',
            'Modules/Shop/resources/js/app.js',
        ], $entries, 'Only the configured entries that exist; admin.js is not one.');

        $this->assertSame(['Modules/Shop/resources/css/app.css', 'Modules/Shop/resources/js/app.js'], $this->entries(['module' => 'Shop']));
        $this->assertSame([], $this->entries(['module' => 'Admin']));
        $this->assertSame(1, $this->command('module:vite', ['module' => 'Missing'])[0]);
    }

    public function test_disabled_modules_and_the_configured_entries(): void
    {
        $this->modules(['disabled' => ['Blog', 'Shop/Orders'], 'vite' => ['entries' => ['resources/js/app.js', 'resources/js/admin.js']]]);

        $this->assertSame(['Modules/Shop/resources/js/app.js', 'Modules/Shop/resources/js/admin.js'], $this->entries());
    }

    public function test_the_directive_renders_the_module_entries_from_the_manifest(): void
    {
        $this->modules();
        $this->write('public/build/manifest.json', (string) json_encode([
            'Modules/Shop/resources/css/app.css' => ['file' => 'assets/shop-1a.css', 'src' => 'Modules/Shop/resources/css/app.css', 'isEntry' => true],
            'Modules/Shop/resources/js/app.js' => ['file' => 'assets/shop-2b.js', 'src' => 'Modules/Shop/resources/js/app.js', 'isEntry' => true],
            'Modules/Shop/resources/js/admin.js' => ['file' => 'assets/admin-3c.js', 'src' => 'Modules/Shop/resources/js/admin.js', 'isEntry' => true],
        ]));

        $html = $this->render("@moduleVite('Shop')");
        $this->assertStringContainsString('build/assets/shop-1a.css', $html);
        $this->assertStringContainsString('build/assets/shop-2b.js', $html);
        $this->assertStringNotContainsString('admin-3c', $html);

        $html = $this->render("@moduleVite('Shop', 'resources/js/admin.js')");
        $this->assertStringContainsString('build/assets/admin-3c.js', $html);
        $this->assertStringNotContainsString('shop-2b', $html);

        $this->assertSame('', trim($this->render("@moduleVite('Admin')")), 'A module without entries renders nothing.');
    }

    public function test_the_directive_refuses_an_unknown_module(): void
    {
        $this->modules();

        try {
            $this->render("@moduleVite('Missing')");
            $this->fail('An unknown module is refused.');
        } catch (\Throwable $e) {
            $previous = $e->getPrevious() ?? $e;
            $this->assertInstanceOf(InvalidArgumentException::class, $previous);
            $this->assertStringContainsString('Module [Missing] does not exist.', $previous->getMessage());
        }
    }

    public function test_the_vite_helper_reads_the_entries_from_artisan(): void
    {
        $node = (new ExecutableFinder)->find('node');

        if ($node === null) {
            $this->markTestSkipped('node is not installed.');
        }

        // A stand-in for "php artisan": it prints the arguments it was given, as module:vite prints JSON.
        $artisan = $this->write('fake-artisan.js', 'process.stdout.write(JSON.stringify(process.argv.slice(2)));');
        $helper = realpath(__DIR__.'/../../resources/vite/modulator.js');
        $script = sprintf(
            'import(%s).then(({ modules }) => console.log(JSON.stringify(modules({ php: process.execPath, artisan: %s }))));',
            json_encode('file:///'.ltrim(str_replace('\\', '/', (string) $helper), '/')),
            json_encode($artisan)
        );

        $process = new Process([$node, '-e', $script]);
        $process->mustRun();

        $this->assertSame(['module:vite', '--no-ansi'], json_decode(trim($process->getOutput()), true));
    }
}
