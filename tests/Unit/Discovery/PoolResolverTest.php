<?php

namespace Pharaonic\Laravel\Modulator\Tests\Unit\Discovery;

use Illuminate\Container\Container;
use InvalidArgumentException;
use Pharaonic\Laravel\Modulator\Contracts\Pool;
use Pharaonic\Laravel\Modulator\DirectoryPool;
use Pharaonic\Laravel\Modulator\Discovery\PoolResolver;
use Pharaonic\Laravel\Modulator\Tests\Fixtures\TenantPool;
use Pharaonic\Laravel\Modulator\Tests\Unit\FilesystemTestCase;

class PoolResolverTest extends FilesystemTestCase
{
    private function resolver(): PoolResolver
    {
        return new PoolResolver(new Container, $this->base.'/vendor');
    }

    /**
     * @param  array<int, array<string, mixed>>  $packages
     */
    private function installed(array $packages): void
    {
        $this->write('vendor/composer/installed.json', (string) json_encode(['packages' => $packages]));
    }

    public function test_configured_pools_accept_arrays_and_pool_classes(): void
    {
        $pools = $this->resolver()->configured([
            'local' => ['path' => '/srv/Modules', 'namespace' => 'Modules', 'priority' => 100],
            'app' => ['path' => '/srv/app/Modules', 'namespace' => 'App\\Modules', 'priority' => 90, 'legacy' => true],
            'tenant' => TenantPool::class,
        ]);

        $this->assertSame(['local', 'app', 'tenant'], array_map(static function (Pool $pool): string {
            return $pool->name();
        }, $pools));
        $this->assertInstanceOf(DirectoryPool::class, $pools[1]);
        $this->assertTrue($pools[1]->isLegacy());
        $this->assertSame('App\\Modules', $pools[1]->namespace());
    }

    /**
     * @dataProvider invalidPools
     *
     * @param  mixed  $pools
     */
    public function test_invalid_configured_pools_are_rejected($pools, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $this->resolver()->configured($pools);
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public function invalidPools(): array
    {
        return [
            'not an array' => ['Modules', 'must be an array'],
            'no path' => [['local' => ['namespace' => 'Modules']], 'needs a "path"'],
            'bad namespace' => [['local' => ['path' => '/x', 'namespace' => 'Mod ules']], 'invalid "namespace"'],
            'bad priority' => [['local' => ['path' => '/x', 'priority' => '100']], '"priority" must be an integer'],
            'not a pool class' => [['local' => \stdClass::class], 'must implement'],
        ];
    }

    public function test_packages_declare_pools_in_composer_extra(): void
    {
        $this->directory('vendor/acme/shop/modules/Shop/app');
        $this->directory('vendor/acme/blog/modules');
        $this->directory('vendor/acme/blog/extra');
        $this->installed([
            ['name' => 'acme/shop', 'install-path' => '../acme/shop', 'extra' => ['pharaonic' => ['modulator' => ['pools' => [['path' => 'modules']]]]]],
            ['name' => 'acme/blog', 'install-path' => '../acme/blog', 'extra' => ['pharaonic' => ['modulator' => ['pools' => [
                ['path' => 'modules', 'namespace' => 'Acme\\Modules', 'priority' => 60],
                ['path' => 'extra'],
            ]]]]],
            ['name' => 'laravel/framework', 'install-path' => '../laravel/framework'],
        ]);

        $result = $this->resolver()->packages();

        $this->assertSame([], $result['diagnostics']);
        $this->assertSame(['acme/blog', 'acme/blog:2', 'acme/shop'], array_map(static function (Pool $pool): string {
            return $pool->name();
        }, $result['pools']));
        $this->assertSame('Acme\\Modules', $result['pools'][0]->namespace());
        $this->assertSame(60, $result['pools'][0]->priority());
        $this->assertSame(PoolResolver::PACKAGE_PRIORITY, $result['pools'][2]->priority());
        $this->assertSame('acme/shop', $result['pools'][2]->package());
        $this->assertSame(realpath($this->base.'/vendor/acme/shop/modules'), $result['pools'][2]->path());
    }

    public function test_package_pool_paths_cannot_escape_the_package(): void
    {
        $this->directory('vendor/acme/shop');
        $this->directory('vendor/acme/other/modules');
        $this->installed([
            ['name' => 'acme/shop', 'install-path' => '../acme/shop', 'extra' => ['pharaonic' => ['modulator' => ['pools' => [['path' => '../other/modules'], ['path' => 'missing'], ['path' => 5]]]]]],
        ]);

        $result = $this->resolver()->packages();

        $this->assertSame([], $result['pools']);
        $this->assertCount(3, $result['diagnostics']);
        $this->assertStringContainsString('does not exist inside the package', $result['diagnostics'][0]->message);
    }

    public function test_composer_1_installed_json_is_supported(): void
    {
        $this->directory('vendor/acme/shop/modules');
        $this->write('vendor/composer/installed.json', (string) json_encode([
            ['name' => 'acme/shop', 'extra' => ['pharaonic' => ['modulator' => ['pools' => ['path' => 'modules']]]]],
        ]));

        $this->assertSame('acme/shop', $this->resolver()->packages()['pools'][0]->name());
    }
}
