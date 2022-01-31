<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands\Packages;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Pharaonic\Laravel\Modulator\Core\Command;

class TranslatableMake extends Command
{
    protected $description = 'Create translatable Models & Migrations for a module';
    protected $signature = 'module:make:translatable {module} {name}
                            {--m|migration : Create a new migration file for the model}';

    public function exec()
    {
        if (!$this->moduleExists()) return;

        // CREATE MODELS DIRECTORY IF NOT FOUND
        if (!file_exists($models = $this->getPath('Models')))
            File::makeDirectory($models, 0777, true, true);

        $this->generateMainModel();
        $this->generateTranslatableModel();
    }

    private function generateMainModel()
    {
        $this->table = Str::snake($this->name);
        $this->id = $this->table . '_id';
        $this->table = Str::plural($this->table);

        $content = str_replace('{{ class }}', $this->name, file_get_contents(__DIR__ . '/../stubs/mode.translatable.stub'));
        $content = str_replace('{{ namespace }}', $this->getNamespace('Models'), $content);
        $content = str_replace('use HasFactory;', 'use HasFactory, Translatable;', $content);
        $content = str_replace('{{ table }}', $this->table, $content);

        // SAVING MODEL
        if (file_exists($path = $this->getPath('Models/' . $this->fullName . '.php'))) {
            $this->error('Model is already exists!');
            return false;
        }

        if (!File::isDirectory($dir = dirname($path)))
            File::makeDirectory($dir, 0755, true, true);

        if (File::put($path, $content)) {
            $this->info('Model created successfully.');
        } else {
            $this->warn('There is something wrong.');
        }

        // CREATE MIGRATION
        if ($this->option('migration')) {
            $command = 'module:make:migration ' . $this->module . ' create_' . $this->table . '_table';
            Artisan::call($command, [], $this->getOutput());
        }
    }

    private function generateTranslatableModel()
    {
        $this->mainTable = $this->table;
        $this->table = Str::snake(Str::plural($this->name . 'Translations'));
        $this->name .= 'Translation';
        $this->fullName .= 'Translation';

        $content = str_replace('{{ class }}', $this->name, file_get_contents(__DIR__ . '/../stubs/mode.translatabled.stub'));
        $content = str_replace('{{ namespace }}', $this->getNamespace('Models'), $content);
        $content = str_replace('{{ table }}', $this->table, $content);
        $content = str_replace('{{ id }}', $this->id, $content);

        // SAVING TRANSLATABLE-MODEL
        if (file_exists($path = $this->getPath('Models/' . $this->fullName . '.php'))) {
            $this->error('Translatable-Model is already exists!');
            return false;
        }

        if (!File::isDirectory($dir = dirname($path)))
            File::makeDirectory($dir, 0755, true, true);

        if (File::put($path, $content)) {
            $this->info('Translatable-Model created successfully.');
        } else {
            $this->warn('There is something wrong.');
        }

        // CREATE MIGRATION
        if ($this->option('migration')) {
            $command = 'module:make:migration ' . $this->module . ' create_' . $this->table . '_table';
            Artisan::call($command, [], $this->getOutput());

            $file = File::glob(module_database_path($this->module, 'migrations/*create_' . $this->table . '_table.php'));
            if (!empty($file)) {
                $file   = $file[0];
                $content    = File::get($file);
                $hasId      = strpos($content, '$table->id()') !== false;

                $content    = str_replace(
                    '$table->timestamps();',
                    PHP_EOL . '            $table->string(\'locale\')->index();' . PHP_EOL .
                        '            $table->unsigned' . ($hasId ? 'Big' : '') . 'Integer(\'' . $this->id . '\');' . PHP_EOL .
                        '            $table->unique([\'' . $this->id . '\', \'locale\']);' . PHP_EOL .
                        '            $table->foreign(\'' . $this->id . '\')->references(\'id\')->on(\'' . $this->mainTable . '\')->onDelete(\'cascade\');',
                    $content
                );
                File::put($file, $content);
            }
        }
    }
}
