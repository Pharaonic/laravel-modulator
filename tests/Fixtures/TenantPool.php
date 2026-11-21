<?php

namespace Pharaonic\Laravel\Modulator\Tests\Fixtures;

use Pharaonic\Laravel\Modulator\Contracts\Pool;

final class TenantPool implements Pool
{
    /**
     * @var string|null
     */
    public static $path;

    public function name(): string
    {
        return 'tenant';
    }

    public function priority(): int
    {
        return 80;
    }

    public function path(): string
    {
        return self::$path ?? sys_get_temp_dir().'/modulator-no-tenant';
    }

    public function namespace(): string
    {
        return 'Tenant\\Modules';
    }

    public function package(): ?string
    {
        return null;
    }
}
