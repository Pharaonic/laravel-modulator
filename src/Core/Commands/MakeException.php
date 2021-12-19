<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\File;
use Pharaonic\Laravel\Modulator\Core\Command;

class MakeException extends Command
{
    protected $description = 'Create a new custom exception class of a module';
    protected $signature = 'module:make:exception {module : Module\'s name} {name : Channel\'s name}
                            {--render : Create the exception with an empty render method}
                            {--report : Create the exception with an empty report method}';

    public function exec()
    {
        if (!$this->moduleExists()) return;

        // CREATE EXCEPTIONS DIRECTORY IF NOT FOUND
        if (!file_exists($exceptions = $this->getPath('Exceptions')))
            File::makeDirectory($exceptions, 0777, true, true);

        $content = str_replace('{{ class }}', $this->name, file_get_contents(__DIR__ . '/stubs/exception.stub'));
        $content = str_replace('{{ namespace }}', $this->getNamespace('Exceptions'), $content);

        if ($this->option('report')) {
            $content = str_replace('{{ REPORT:START }}', '', $content);
            $content = str_replace('{{ REPORT:END }}', '', $content);
        } else {
            $content = $this->deleteStringBetween('{{ REPORT:START }}', '{{ REPORT:END }}', $content);
        }

        if ($this->option('render')) {
            $content = str_replace('{{ RENDER:START }}', '', $content);
            $content = str_replace('{{ RENDER:END }}', '', $content);
        } else {
            $content = $this->deleteStringBetween('{{ RENDER:START }}', '{{ RENDER:END }}', $content);
        }

        if (!$this->option('render') && !$this->option('report')) {
            $content = str_replace("{\n}", "{\n    //\n}", $content);
        } else {
            $content = str_replace("}\n\n", "}\n", $content);
            $content = str_replace("{\n\n", "{\n", $content);
        }

        // SAVING EXCEPTION
        if (file_exists($path = $this->getPath('Exceptions/' . $this->fullName . '.php'))) {
            $this->error('Exception is already exists!');
            return false;
        }

        if (!File::isDirectory($dir = dirname($path)))
            File::makeDirectory($dir, 0755, true, true);

        if (File::put($path, $content)) {
            $this->info('Exception created successfully.');
        } else {
            $this->warn('There is something wrong.');
        }
    }

    /**
     * Delete text between 2 strings
     *
     * @param string $beginning
     * @param string $end
     * @param string $string
     * @return string
     */
    function deleteStringBetween($beginning, $end, $string)
    {
        $beginningPos = strpos($string, $beginning);
        $endPos = strpos($string, $end);

        if ($beginningPos === false || $endPos === false)
            return $string;

        $textToDelete = substr($string, $beginningPos, ($endPos + strlen($end)) - $beginningPos);

        return $this->deleteStringBetween($beginning, $end, str_replace($textToDelete, '', $string));
    }
}
