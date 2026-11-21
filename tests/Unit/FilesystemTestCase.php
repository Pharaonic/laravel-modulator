<?php

namespace Pharaonic\Laravel\Modulator\Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use Pharaonic\Laravel\Modulator\Tests\WritesFiles;
use PHPUnit\Framework\TestCase;

abstract class FilesystemTestCase extends TestCase
{
    use WritesFiles;

    /**
     * @var string
     */
    protected $base;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = self::temporaryDirectory('modulator-unit');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->base);

        parent::tearDown();
    }
}
