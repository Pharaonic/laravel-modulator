<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class Make extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:make {name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new module.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->name = $this->argument('name');
        $this->path = module_path($this->name);

        $this->nameNS = str_replace('/', '\\', $this->name);
        $this->name = Str::studly($this->name);
        $this->slug = studlyToSlug($this->name);

        // IF EXISTS
        if (File::isDirectory($this->path))
            return $this->error('  Module is already exists.  ');

        // PREPARE THE MODULE MODULE
        $base = __DIR__ . DIRECTORY_SEPARATOR . 'stubs' . DIRECTORY_SEPARATOR;

        if (file_exists($this->tmp = $base . 'module-tmp'))
            File::deleteDirectory($this->tmp);


        if (File::copyDirectory($base . 'module', $this->tmp)) {
            $this->comment('Creating a new module [' . $this->name . ']');

            // CONFIG
            $this->inject('config/app.php');
            $this->info('DONE : Config');

            // DATABSE
            $this->inject('database/seeders/DatabaseSeeder.php');
            $this->info('DONE : Database');

            // PROVIDERS
            $this->inject('Providers/AppServiceProvider.php');
            $this->inject('Providers/BroadcastServiceProvider.php');
            $this->inject('Providers/EventServiceProvider.php');
            $this->inject('Providers/RouteServiceProvider.php');
            $this->info('DONE : Providers');

            // RESOURCES
            $this->inject('resources/lang/en/example.php');
            $this->inject('resources/views/example.blade.php');
            $this->info('DONE : Resources');

            // ROUTES
            $this->inject('routes/api.php');
            $this->inject('routes/channels.php');
            $this->inject('routes/console.php');
            $this->inject('routes/web.php');
            $this->info('DONE : Routes');
            $this->newLine(1);

            // COPY TO THE MAIN PATH
            File::copyDirectory($this->tmp, $this->path);
            $this->info('Module created successfully.');

            // DELETE TEMP
            File::deleteDirectory($this->tmp);
        } else {
            $this->error('  There\'s something wrong!');
        }

        return true;
    }

    /**
     * Inject the data
     * 
     * @param string $path
     * @return void
     */
    protected function inject(string $path)
    {
        $path = $this->tmp . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, explode('/', $path));
        $content = file_get_contents($path);

        $content = str_replace('{{ module-name-namespace-double }}', str_replace('\\', '\\\\', $this->nameNS), $content);
        $content = str_replace('{{ module-name-namespace }}', $this->nameNS, $content);
        $content = str_replace('{{ module-name }}', $this->name, $content);
        $content = str_replace('{{ module-slug }}', $this->slug, $content);

        file_put_contents($path, $content);
    }
}
