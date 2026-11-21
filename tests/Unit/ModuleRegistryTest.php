<?php

namespace Pharaonic\Laravel\Modulator\Tests\Unit;

use Pharaonic\Laravel\Modulator\Discovery\Source;
use Pharaonic\Laravel\Modulator\ModuleRegistry;
use Pharaonic\Laravel\Modulator\Support\Diagnostic;
use PHPUnit\Framework\TestCase;

class ModuleRegistryTest extends TestCase
{
    /**
     * @param  list<string>  $dependencies
     */
    private function source(string $id, string $pool = 'local', int $priority = 100, array $dependencies = [], ?string $package = null): Source
    {
        return new Source($id, $pool, $priority, '/'.$pool.'/'.$id, 'Modules\\'.str_replace('/', '\\', $id), $package, false, $dependencies);
    }

    /**
     * @return list<string>
     */
    private function errors(ModuleRegistry $registry): array
    {
        return array_values(array_map(static function (Diagnostic $diagnostic): string {
            return $diagnostic->message;
        }, array_filter($registry->diagnostics(), static function (Diagnostic $diagnostic): bool {
            return $diagnostic->isError();
        })));
    }

    public function test_every_module_is_enabled_unless_disabled(): void
    {
        $registry = new ModuleRegistry([$this->source('Shop'), $this->source('Blog')]);

        $this->assertSame(['Blog', 'Shop'], array_keys($registry->all()));
        $this->assertSame(['Blog', 'Shop'], array_keys($registry->enabled()));
        $this->assertTrue($registry->find('Shop')->isEnabled());
        $this->assertNull($registry->find('Shop')->disabledReason());
        $this->assertNull($registry->find('Missing'));
    }

    public function test_the_highest_priority_source_wins_and_others_are_shadowed(): void
    {
        $registry = new ModuleRegistry([
            $this->source('Shop', 'acme/shop', 50, [], 'acme/shop'),
            $this->source('Shop', 'local', 100),
        ]);

        $this->assertSame('local', $registry->find('Shop')->pool());
        $this->assertSame(['local', 'acme/shop'], array_map(static function (Source $source): string {
            return $source->pool;
        }, $registry->sources('Shop')));
        $this->assertSame([], $this->errors($registry));
    }

    public function test_equal_priorities_are_a_collision_whatever_the_order(): void
    {
        foreach ([['a', 'b'], ['b', 'a']] as [$first, $second]) {
            $registry = new ModuleRegistry([$this->source('Shop', $first, 100), $this->source('Shop', $second, 100)]);

            $this->assertNull($registry->find('Shop'));
            $this->assertStringContainsString('same priority (100) by pools [a], [b]', $this->errors($registry)[0]);
        }
    }

    public function test_a_disabled_winner_never_falls_back_to_a_shadowed_source(): void
    {
        $registry = new ModuleRegistry([
            $this->source('Shop', 'acme/shop', 50, [], 'acme/shop'),
            $this->source('Shop', 'local', 100),
        ], ['Shop']);

        $this->assertSame('local', $registry->find('Shop')->pool());
        $this->assertFalse($registry->find('Shop')->isEnabled());
        $this->assertSame([], $registry->enabled());
        $this->assertTrue($registry->isDisabledInConfig('Shop'));
    }

    public function test_a_disabled_parent_disables_its_children_without_touching_their_state(): void
    {
        $registry = new ModuleRegistry([
            $this->source('Commerce'),
            $this->source('Commerce/Checkout'),
            $this->source('Commerce/Checkout/Payments'),
        ], ['Commerce']);

        $this->assertSame('Commerce', $registry->find('Commerce/Checkout')->parent());
        $this->assertSame(['Commerce/Checkout'], $registry->find('Commerce')->children());
        $this->assertFalse($registry->find('Commerce/Checkout/Payments')->isEnabled());
        $this->assertSame('Disabled because its parent [Commerce/Checkout] is disabled.', $registry->find('Commerce/Checkout/Payments')->disabledReason());
        $this->assertFalse($registry->isDisabledInConfig('Commerce/Checkout'));
    }

    public function test_a_namespace_group_is_not_a_parent(): void
    {
        // "Commerce" has no source: it is only a group.
        $registry = new ModuleRegistry([$this->source('Commerce/Checkout')], ['Commerce']);

        $this->assertNull($registry->find('Commerce'));
        $this->assertNull($registry->find('Commerce/Checkout')->parent());
        $this->assertTrue($registry->find('Commerce/Checkout')->isEnabled());
    }

    public function test_missing_and_disabled_dependencies_block_activation(): void
    {
        $registry = new ModuleRegistry([
            $this->source('Orders', 'local', 100, ['Catalog']),
            $this->source('Reports', 'local', 100, ['Analytics']),
            $this->source('Analytics'),
        ], ['Analytics']);

        $this->assertSame('Disabled because its dependency [Catalog] is missing.', $registry->find('Orders')->disabledReason());
        $this->assertSame('Disabled because its dependency [Analytics] is disabled.', $registry->find('Reports')->disabledReason());
        $this->assertSame(['Module [Orders] is not enabled. Disabled because its dependency [Catalog] is missing.'], $this->errors($registry));
    }

    public function test_dependency_cycles_disable_every_member(): void
    {
        $registry = new ModuleRegistry([
            $this->source('A', 'local', 100, ['B']),
            $this->source('B', 'local', 100, ['C']),
            $this->source('C', 'local', 100, ['A']),
            $this->source('D', 'local', 100, ['A']),
        ]);

        foreach (['A', 'B', 'C'] as $id) {
            $this->assertFalse($registry->find($id)->isEnabled());
            $this->assertStringContainsString('requirement cycle between [A], [B], [C]', (string) $registry->find($id)->disabledReason());
        }

        $this->assertSame('Disabled because its dependency [A] is disabled.', $registry->find('D')->disabledReason());
    }

    public function test_a_parent_depending_on_its_child_is_a_cycle(): void
    {
        $registry = new ModuleRegistry([
            $this->source('Shop', 'local', 100, ['Shop/Orders']),
            $this->source('Shop/Orders'),
        ]);

        $this->assertFalse($registry->find('Shop')->isEnabled());
        $this->assertFalse($registry->find('Shop/Orders')->isEnabled());
    }

    public function test_enabled_modules_come_in_dependency_order(): void
    {
        $registry = new ModuleRegistry([
            $this->source('Alpha', 'local', 100, ['Zulu']),
            $this->source('Zulu'),
            $this->source('Zulu/Child'),
        ]);

        $this->assertSame(['Zulu', 'Alpha', 'Zulu/Child'], array_keys($registry->enabled()));
    }

    public function test_identities_differing_by_case_are_rejected(): void
    {
        $registry = new ModuleRegistry([$this->source('ShopX'), $this->source('Shopx', 'other')]);

        $this->assertSame([], $registry->all());
        $this->assertStringContainsString('differ only by case', $this->errors($registry)[0]);
    }

    public function test_invalid_and_unknown_disabled_entries_are_reported(): void
    {
        $registry = new ModuleRegistry([$this->source('Shop')], ['../etc', 'Ghost', 42]);

        $this->assertTrue($registry->find('Shop')->isEnabled());
        $this->assertCount(2, $this->errors($registry));
        $this->assertStringContainsString('[Ghost], which is not a discovered module', $registry->diagnostics()[1]->message);
    }

    public function test_a_child_cannot_be_named_after_a_reserved_parent_namespace(): void
    {
        $registry = new ModuleRegistry([$this->source('Shop'), $this->source('Shop/Database')]);

        $this->assertStringContainsString('cannot be named [Database]', $this->errors($registry)[0]);
    }

    public function test_explain_follows_the_chain_down_to_the_cause(): void
    {
        $registry = new ModuleRegistry([
            $this->source('Catalog'),
            $this->source('Shop', 'local', 100, ['Catalog']),
            $this->source('Shop/Orders'),
        ], ['Catalog']);

        $this->assertSame([
            ['module' => 'Shop/Orders', 'reason' => 'Disabled because its parent [Shop] is disabled.'],
            ['module' => 'Shop', 'reason' => 'Disabled because its dependency [Catalog] is disabled.'],
            ['module' => 'Catalog', 'reason' => 'Disabled in configuration (pharaonic.modulator.disabled).'],
        ], $registry->explain('Shop/Orders'));
        $this->assertSame([], (new ModuleRegistry([$this->source('Catalog')]))->explain('Catalog'));
    }

    public function test_dependents_are_transitive_and_exclude_children(): void
    {
        $registry = new ModuleRegistry([
            $this->source('Catalog'),
            $this->source('Catalog/Search'),
            $this->source('Shop', 'local', 100, ['Catalog']),
            $this->source('Checkout', 'local', 100, ['Shop']),
            $this->source('Blog'),
        ], ['Blog', 'Shop']);

        $this->assertSame(['Checkout', 'Shop'], $registry->dependents('Catalog'));
        $this->assertSame([], $registry->dependents('Blog'));
        $this->assertSame(['Blog', 'Shop'], $registry->configuredDisabled());
    }
}
