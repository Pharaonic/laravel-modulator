<?php

namespace Pharaonic\Laravel\Modulator\Core;

use Illuminate\Support\Facades\File;

class ModulesFinder
{
    private $manifestPath;
    public $cached = false;
    public $list = [];

    function __construct()
    {
        $this->manifestPath = app()->bootstrapPath('cache/modulator-providers.php');

        if (File::exists($this->manifestPath)) {
            $this->cached = true;
            $this->list = require($this->manifestPath);
        }
    }

    private function find()
    {
        $this->list = [];
        $modules = modules();

        if (count($modules) > 0) {
            // CREATE MAIN SERVICE PROVIDER
            if (!file_exists($SP = app_path('Modules/ServiceProvider.php'))) {
                File::ensureDirectoryExists(module_path());
                File::copy(str_replace('/', DIRECTORY_SEPARATOR, __DIR__ . '/Core/Commands/stubs/ServiceProvider.php'), $SP);
            }

            foreach ($modules as $module) {
                foreach (getFiles($path = module_path($module, 'Providers')) as $provider) {
                    $provider = str_replace($path . '/', '', substr($provider, 0, -4));
                    $this->list[] = 'App\Modules\\' . str_replace('/', '\\', $module) . '\Providers\\' . $provider;
                }
            }
        }
    }

    private function write()
    {
        if (!is_writable(dirname($this->manifestPath))) {
            throw new Exception('The ' . dirname($this->manifestPath) . ' directory must be present and writable.');
        }

        File::put($this->manifestPath, '<?php return ' . var_export($this->list, true) . ';', true);
    }

    public function build()
    {
        $this->find();
        $this->write();
    }
}
