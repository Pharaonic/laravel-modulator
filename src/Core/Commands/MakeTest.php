<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
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
            if (strpos(
                $phpunitContent = file_get_contents($tests),
                '<testsuite name="Modules"><directory suffix="Test.php">./app/Modules/*/tests</directory></testsuite>'
            ) === false) {
                $phpunitContent = str_replace('</testsuites>', '    <testsuite name="Modules"><directory suffix="Test.php">./app/Modules/*/tests</directory></testsuite>' . PHP_EOL . '    </testsuites>', $phpunitContent);
                File::replace($tests, $phpunitContent);
            }
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
