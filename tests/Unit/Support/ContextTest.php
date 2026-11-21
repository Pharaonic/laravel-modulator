<?php

namespace Pharaonic\Laravel\Modulator\Tests\Unit\Support;

use Pharaonic\Laravel\Modulator\Support\Context;
use Pharaonic\Laravel\Modulator\Tests\Unit\FilesystemTestCase;

class ContextTest extends FilesystemTestCase
{
    /**
     * @var string|false
     */
    private $ci;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ci = getenv('CI');
    }

    protected function tearDown(): void
    {
        putenv($this->ci === false ? 'CI' : 'CI='.$this->ci);

        parent::tearDown();
    }

    public function test_it_keeps_the_selection_per_user_outside_version_control(): void
    {
        $context = new Context($this->base);

        $this->assertNull($context->get());

        $context->set('Shop/Orders');

        $this->assertSame('Shop/Orders', (new Context($this->base))->get());
        $this->assertFileExists($this->base.'/framework/modulator/.gitignore');
        $this->assertCount(1, glob($this->base.'/framework/modulator/context/*.json') ?: []);

        $this->assertTrue($context->clear());
        $this->assertNull($context->get());
    }

    public function test_it_is_trusted_only_on_interactive_consoles_outside_ci_and_production(): void
    {
        putenv('CI');
        $this->assertTrue(Context::isTrusted(true, false));
        $this->assertFalse(Context::isTrusted(false, false));
        $this->assertFalse(Context::isTrusted(true, true));

        putenv('CI=true');
        $this->assertFalse(Context::isTrusted(true, false));

        putenv('CI=false');
        $this->assertTrue(Context::isTrusted(true, false));
    }
}
