<?php

// Modules Path
if (! function_exists('modules_path')) {
    function modules_path()
    {
        return module_path();
    }
}

// Module Path
if (! function_exists('module_path')) {
    function module_path(?string $module = null)
    {
        return app_path('Modules' . ($module ? DIRECTORY_SEPARATOR . $module : null));
    }
}

// Config Path
if (! function_exists('module_config_path')) {
    function module_config_path(string $module)
    {
        return module_path($module) . DIRECTORY_SEPARATOR . 'config';
    }
}

// Database Path
if (! function_exists('module_database_path')) {
    function module_database_path(string $module)
    {
        return module_path($module) . DIRECTORY_SEPARATOR . 'database';
    }
}

// Resources Path
if (! function_exists('module_resource_path')) {
    function module_resource_path(string $module)
    {
        return module_path($module) . DIRECTORY_SEPARATOR . 'resources';
    }
}