<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Facades;

use Illuminate\Support\Facades\Facade;
use Pharaonic\Laravel\Modulator\ModuleRegistry;

/**
 * @method static array<string, \Pharaonic\Laravel\Modulator\Module> all()
 * @method static array<string, \Pharaonic\Laravel\Modulator\Module> enabled()
 * @method static \Pharaonic\Laravel\Modulator\Module|null find(string $id)
 *
 * @see ModuleRegistry
 */
final class Modules extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ModuleRegistry::class;
    }
}
