<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Support;

use Illuminate\Contracts\Container\Container;
use LogicException;

/**
 * A typed container lookup: Laravel 11.0 types make() as mixed, later 11.x
 * releases as the class asked for, so the check lives here once.
 *
 * @internal
 */
final class Resolve
{
    /**
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @return T
     *
     * @throws LogicException When the binding is not an instance of $class.
     */
    public static function make(Container $container, string $class): object
    {
        $object = $container->make($class);

        if (! $object instanceof $class) {
            throw new LogicException(sprintf('[%s] is not available.', $class));
        }

        return $object;
    }
}
