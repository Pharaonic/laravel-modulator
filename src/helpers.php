<?php

declare(strict_types=1);

use Pharaonic\Laravel\Modulator\Facades\Modules;
use Pharaonic\Laravel\Modulator\Support\Identity;

if (! function_exists('module_path')) {
    /**
     * The root directory of a module, or a path inside it.
     *
     * Without a module, returns the 1.x app/Modules directory (deprecated).
     *
     * @throws InvalidArgumentException When the identity is invalid or no module has it.
     */
    function module_path(?string $module = null, ?string $extra = null): string
    {
        if ($module === null || $module === '') {
            return app_path('Modules');
        }

        Identity::assert($module);
        $found = Modules::find($module);

        if ($found === null) {
            throw new InvalidArgumentException(sprintf('Module [%s] does not exist.', $module));
        }

        return $found->path($extra);
    }
}

if (! function_exists('module_config_path')) {
    function module_config_path(string $module, ?string $extra = null): string
    {
        return module_path($module, 'config'.($extra ? '/'.$extra : ''));
    }
}

if (! function_exists('module_database_path')) {
    function module_database_path(string $module, ?string $extra = null): string
    {
        return module_path($module, 'database'.($extra ? '/'.$extra : ''));
    }
}

if (! function_exists('module_resource_path')) {
    function module_resource_path(string $module, ?string $extra = null): string
    {
        return module_path($module, 'resources'.($extra ? '/'.$extra : ''));
    }
}
