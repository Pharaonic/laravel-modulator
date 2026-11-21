<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Pharaonic\Laravel\Modulator\Actions\MakeModule;
use Pharaonic\Laravel\Modulator\Support\Identity;
use RuntimeException;

final class ModuleMakeCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'module:make
                            {module : The module identity, e.g. Shop or Commerce/Checkout}
                            {--plain : Only create app/ and the service provider}';

    /**
     * @var string
     */
    protected $description = 'Create a new module in the generation pool';

    public function handle(MakeModule $make): int
    {
        $id = $this->argument('module');

        try {
            $id = Identity::assert(is_string($id) ? $id : '');
            $module = $make->handle($id, (bool) $this->option('plain'));
        } catch (InvalidArgumentException|RuntimeException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        $this->info(sprintf('Module [%s] created at [%s].', $id, $module['path']));
        $this->line(sprintf('Namespace: %s\\ (app/). Add classes with module:make:*, e.g. "php artisan module:make:model %s Product".', $module['namespace'], $id));

        return 0;
    }
}
