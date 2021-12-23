<?php

namespace Pharaonic\Laravel\Modulator\Core;

use Illuminate\Console\Command as ConsoleCommand;
use Illuminate\Support\Str;

class Command extends ConsoleCommand
{
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Prepare The Test
     *
     * @return void
     */
    protected function prepare()
    {
        $this->module   = $this->argument('module');
        $this->slug     = studlyToSlug($this->module);

        if ($this->hasArgument('name')) {
            $this->fullName = $this->argument('name');
            $this->sliceName = explode('/', $this->fullName);
            $this->name = Str::studly(array_pop($this->sliceName));
            $this->sliceName = implode(DIRECTORY_SEPARATOR, $this->sliceName);
        }
    }

    /**
     * Generate module namespace
     *
     * @param string|null $ns
     * @return string
     */
    protected function getNamespace(?string $ns = null)
    {
        if ($ns) {
            $ns = explode('/', trim($ns, '/') . ($this->sliceName ? '/' . $this->sliceName : null));
            $ns = '\\' . implode('\\', $ns);
        }

        return \App\Modules::class . '\\' . $this->module . $ns;
    }

    /**
     * Check Module Existance
     *
     * @return bool
     */
    protected function moduleExists()
    {
        if (!file_exists(module_path($this->module))) {
            $this->error('  Module has not been found.  ');
            return false;
        }

        return true;
    }

    /**
     * Get Full Path
     *
     * @param string $path
     * @return string
     */
    protected function getPath(string $path)
    {
        return module_path($this->module, $path);
    }

    /**
     * Get Short Path
     *
     * @param string $path
     * @return string
     */
    protected function getShortPath(string $path)
    {
        $path = 'app/Modules/' . $this->module . '/' .  $path;
        return str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    /**
     * Append a text to the name
     *
     * @param boolean $condition
     * @param string $name
     * @return void
     */
    protected function appendName(bool $condition, string $text)
    {
        if ($condition) {
            $this->name .= $text;
            $this->fullName .= $text;
        }
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->prepare();
        $this->exec();
    }
}
