<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Support;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Module identities: StudlyCase segments joined by "/" (e.g. "Commerce/Checkout").
 *
 * @internal
 */
final class Identity
{
    /**
     * The deepest hierarchy a module identity may have.
     */
    public const MAX_DEPTH = 16;

    /**
     * Segment names a module can never use: "Modules" holds child modules.
     */
    public const RESERVED = ['Modules'];

    /**
     * Child names a real parent module's children cannot use, because the parent
     * maps "Database\Factories", "Database\Seeders" and "Tests" below its namespace.
     */
    public const RESERVED_CHILDREN = ['Database', 'Tests'];

    public static function isValidSegment(string $segment): bool
    {
        return preg_match('/^[A-Z][A-Za-z0-9]*$/', $segment) === 1
            && ! in_array($segment, self::RESERVED, true);
    }

    public static function isValid(string $id): bool
    {
        $segments = explode('/', $id);

        if (count($segments) > self::MAX_DEPTH) {
            return false;
        }

        foreach ($segments as $segment) {
            if (! self::isValidSegment($segment)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate an identity given by a user (CLI argument, configuration entry).
     *
     * @throws InvalidArgumentException
     */
    public static function assert(string $id): string
    {
        if (! self::isValid($id)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid module identity [%s]. Use StudlyCase segments separated by "/" (e.g. "Shop" or "Commerce/Checkout"), at most %d levels deep, without "Modules", "..", backslashes or absolute paths.',
                $id,
                self::MAX_DEPTH
            ));
        }

        return $id;
    }

    /**
     * @return list<string>
     */
    public static function segments(string $id): array
    {
        return explode('/', $id);
    }

    public static function name(string $id): string
    {
        $segments = self::segments($id);

        return $segments[count($segments) - 1];
    }

    /**
     * Ancestor identities, nearest first ("A/B/C" gives "A/B", "A").
     *
     * @return list<string>
     */
    public static function ancestors(string $id): array
    {
        $segments = self::segments($id);
        $ancestors = [];

        while (count($segments) > 1) {
            array_pop($segments);
            $ancestors[] = implode('/', $segments);
        }

        return $ancestors;
    }

    /**
     * The namespace suffix of an identity ("Commerce/Checkout" gives "Commerce\Checkout").
     */
    public static function namespace(string $id): string
    {
        return str_replace('/', '\\', $id);
    }

    /**
     * The slug used for view, translation and configuration namespaces.
     *
     * Same algorithm as 1.x studlyToSlug(): "Commerce/Checkout" gives
     * "commerce-checkout" and "OrderItems" gives "order-items".
     */
    public static function slug(string $id): string
    {
        return Str::slug(strtolower(trim((string) preg_replace('/[A-Z-]/', ' $0', $id))));
    }
}
