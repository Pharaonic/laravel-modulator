<?php

namespace Pharaonic\Laravel\Modulator\Tests\Unit\Support;

use InvalidArgumentException;
use Pharaonic\Laravel\Modulator\Support\Identity;
use PHPUnit\Framework\TestCase;

class IdentityTest extends TestCase
{
    /**
     * @dataProvider validIdentities
     */
    public function test_it_accepts_studly_identities(string $id): void
    {
        $this->assertTrue(Identity::isValid($id));
        $this->assertSame($id, Identity::assert($id));
    }

    /**
     * @return array<string, array{string}>
     */
    public function validIdentities(): array
    {
        return [
            'single' => ['Shop'],
            'nested' => ['Commerce/Checkout'],
            'deep' => ['Commerce/Checkout/Payments/Stripe'],
            'digits' => ['Shop2'],
        ];
    }

    /**
     * @dataProvider invalidIdentities
     */
    public function test_it_rejects_traversal_and_invalid_names(string $id): void
    {
        $this->assertFalse(Identity::isValid($id));

        $this->expectException(InvalidArgumentException::class);
        Identity::assert($id);
    }

    /**
     * @return array<string, array{string}>
     */
    public function invalidIdentities(): array
    {
        return [
            'empty' => [''],
            'parent traversal' => ['..'],
            'nested traversal' => ['Shop/../..'],
            'absolute' => ['/etc'],
            'windows absolute' => ['C:\\Windows'],
            'backslash' => ['Shop\\Orders'],
            'trailing slash' => ['Shop/'],
            'double slash' => ['Shop//Orders'],
            'lowercase' => ['shop'],
            'option injection' => ['Shop --force'],
            'shell injection' => ['Shop;rm -rf /'],
            'reserved segment' => ['Shop/Modules/Orders'],
            'too deep' => [implode('/', array_fill(0, Identity::MAX_DEPTH + 1, 'A'))],
        ];
    }

    public function test_it_keeps_the_1x_slug_algorithm(): void
    {
        $this->assertSame('shop', Identity::slug('Shop'));
        $this->assertSame('order-items', Identity::slug('OrderItems'));
        $this->assertSame('commerce-checkout', Identity::slug('Commerce/Checkout'));
    }

    public function test_it_lists_ancestors_nearest_first(): void
    {
        $this->assertSame(['A/B', 'A'], Identity::ancestors('A/B/C'));
        $this->assertSame([], Identity::ancestors('A'));
        $this->assertSame('A\\B\\C', Identity::namespace('A/B/C'));
        $this->assertSame('C', Identity::name('A/B/C'));
    }
}
