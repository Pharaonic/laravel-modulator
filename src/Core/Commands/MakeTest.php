<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\File;
use Pharaonic\Laravel\Modulator\Core\Command;

class MakeTest extends Command
{
    protected $description = 'Create a new test class of a module';
    protected $signature = 'module:make:test {module : Module\'s name} {name : Test\'s name}
                            {--u|unit : Create a unit test.}
                            {--p|pest : Create a Pest test.}';

    /**
     * The console command description.
     *
     * @var string
     */
    public function exec()
    {
        if (!$this->moduleExists()) return;

        // APPEND MODULES TESTS TO PHPUNIT.XML
        if (file_exists($tests = base_path('phpunit.xml'))) {
            $phpunitContent = file_get_contents($tests);

            if (!str_contains($phpunitContent, './app/Modules/*/tests/Feature') || !str_contains($phpunitContent, './app/Modules/*/tests/Unit')) {
                $phpunitContent = str_replace(
                    [
                        '<directory>tests/Unit</directory>',
                        '<directory>tests/Feature</directory>',
                        PHP_EOL . '        <testsuite name="Modules"><directory suffix="Test.php">./app/Modules/*/tests</directory></testsuite>'
                    ],
                    [
                        '<directory>tests/Unit</directory>' . PHP_EOL . '            <directory>./app/Modules/*/tests/Unit</directory>',
                        '<directory>tests/Feature</directory>' . PHP_EOL . '            <directory>./app/Modules/*/tests/Feature</directory>',
                        '',
                    ],
                    $phpunitContent
                );

                File::replace($tests, $phpunitContent);
            }
        }

        if (file_exists($tests = base_path('tests/Pest.php'))) {
            $pestContent = file_get_contents($tests);

            if (str_contains($pestContent, "'Feature'") && !str_contains($pestContent, '../app/Modules/*/tests/Feature')) {
                $pestContent = str_replace(
                    "'Feature'",
                    "'Feature', '../app/Modules/*/tests/Feature'",
                    $pestContent
                );
            }

            if (
                (str_contains($pestContent, "'Unit'") || str_contains($pestContent, '"Unit"'))
                && !str_contains($pestContent, '../app/Modules/*/tests/Unit')
            ) {
                $pestContent = str_replace(
                    ["'Unit'", '"Unit"'],
                    "'Unit', '../app/Modules/*/tests/Unit'",
                    $pestContent
                );
            }

            File::replace($tests, $pestContent);
        }


        // CREATE TESTS DIRECTORY IF NOT FOUND
        if (!file_exists($tests = module_path($this->module, 'tests'))) {
            File::makeDirectory($tests, 0777, true, true);
            File::makeDirectory($tests . DIRECTORY_SEPARATOR . 'Feature', 0777, true, true);
            File::makeDirectory($tests . DIRECTORY_SEPARATOR . 'Unit', 0777, true, true);
        }

        // TEST NAME
        $this->appendName(substr(strtolower($this->name), -4) != 'test', 'Test');

        // STUB
        $stubPath = __DIR__ . '/stubs/' . ($this->option('pest') ? 'pest.' : null) . ($this->option('unit') ? 'test.unit' : 'test') . '.stub';
        $stubContent = file_get_contents($stubPath);

        // PREPARE STUB CONTENT
        if (strpos($stubContent, 'namespace') !== false) {
            $stubContent = str_replace('{{ class }}', $this->name, $stubContent);
            $stubContent = str_replace(
                '{{ namespace }}',
                $this->getNamespace('tests/' . ($this->option('unit') ? 'Unit' : 'Feature')),
                $stubContent
            );
        }

        // SAVING TEST
        if (file_exists($path = $this->getPath('tests/' . ($this->option('unit') ? 'Unit' : 'Feature') . '/' . $this->fullName . '.php'))) {
            $this->error('Test is already exists!');
            return false;
        }

        if (!File::isDirectory($dir = dirname($path)))
            File::makeDirectory($dir, 0755, true, true);

        if (File::put($path, $stubContent)) {
            $this->info('Test created successfully.');
        } else {
            $this->warn('There is something wrong.');
        }
    }
}
