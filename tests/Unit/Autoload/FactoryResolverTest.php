<?php

namespace Pharaonic\Laravel\Modulator\Tests\Unit\Autoload;

use Pharaonic\Laravel\Modulator\Autoload\FactoryResolver;
use PHPUnit\Framework\TestCase;

class FactoryResolverTest extends TestCase
{
    public function test_module_models_use_the_module_factories_namespace(): void
    {
        $namespaces = ['Modules\\Shop' => false, 'Modules\\Shop\\Orders' => false];

        $this->assertSame('Modules\\Shop\\Database\\Factories\\ProductFactory', FactoryResolver::resolve('Modules\\Shop\\Models\\Product', $namespaces, 'App\\', 'Database\\Factories\\'));
        $this->assertSame('Modules\\Shop\\Database\\Factories\\Catalog\\ItemFactory', FactoryResolver::resolve('Modules\\Shop\\Models\\Catalog\\Item', $namespaces, 'App\\', 'Database\\Factories\\'));
        $this->assertSame('Modules\\Shop\\Orders\\Database\\Factories\\OrderFactory', FactoryResolver::resolve('Modules\\Shop\\Orders\\Models\\Order', $namespaces, 'App\\', 'Database\\Factories\\'));
    }

    public function test_legacy_module_models_use_the_1x_factory_namespace(): void
    {
        $namespaces = ['App\\Modules\\Blog' => true, 'App\\Modules\\Blog\\Posts' => true];

        $this->assertSame('App\\Modules\\Blog\\database\\factories\\PostFactory', FactoryResolver::resolve('App\\Modules\\Blog\\Models\\Post', $namespaces, 'App\\', 'Database\\Factories\\'));
        $this->assertSame('App\\Modules\\Blog\\Posts\\database\\factories\\CommentFactory', FactoryResolver::resolve('App\\Modules\\Blog\\Posts\\Models\\Comment', $namespaces, 'App\\', 'Database\\Factories\\'));
    }

    /**
     * Regression: 1.x sent these models back to Factory::resolveFactoryName(),
     * which called the same resolver again until the stack overflowed.
     */
    public function test_other_models_resolve_like_laravel_without_recursion(): void
    {
        $namespaces = ['App\\Modules\\Blog' => true, 'Modules\\Shop' => false];

        $this->assertSame('Database\\Factories\\UserFactory', FactoryResolver::resolve('App\\Models\\User', $namespaces, 'App\\', 'Database\\Factories\\'));
        $this->assertSame('Database\\Factories\\TeamFactory', FactoryResolver::resolve('App\\Team', $namespaces, 'App\\', 'Database\\Factories\\'));
        $this->assertSame('Database\\Factories\\Vendor\\ThingFactory', FactoryResolver::resolve('Vendor\\Thing', $namespaces, 'App\\', 'Database\\Factories\\'));
    }
}
