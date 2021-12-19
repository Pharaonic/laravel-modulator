<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Pharaonic\Laravel\Modulator\Core\Command;

class MakeMail extends Command
{
    protected $description = 'Create a new email class of a module';
    protected $signature = 'module:make:mail {module : Module\'s name} {name : Mail\'s name}
                            {--f|force : Create the class even if the mailable already exists}
                            {--m|markdown=false : Create a new Markdown template for the mailable}
                            {--test : Generate an accompanying PHPUnit test for the Mail}
                            {--pest : Generate an accompanying Pest test for the Mail}';

    public function exec()
    {
        if (!$this->moduleExists()) return;

        // CREATE MAILS DIRECTORY IF NOT FOUND
        if (!file_exists($mails = $this->getPath('Mail')))
            File::makeDirectory($mails, 0777, true, true);

        $content = str_replace('{{ class }}', $this->name, file_get_contents(__DIR__ . '/stubs/mail.stub'));
        $content = str_replace('{{ namespace }}', $this->getNamespace('Mail'), $content);

        $markdown = $this->option('markdown');
        if ($markdown != 'false')
            $content = str_replace("view('view.name')", "markdown('$markdown')", $content);

        // SAVING MAIL
        if (file_exists($path = $this->getPath('Mail/' . $this->fullName . '.php')) && !$this->option('force')) {
            $this->error('Mail is already exists!');
            return false;
        }

        if (!File::isDirectory($dir = dirname($path)))
            File::makeDirectory($dir, 0755, true, true);

        if (File::put($path, $content)) {
            $this->info('Mail created successfully.');
        } else {
            $this->warn('There is something wrong.');
        }

        // CREATE TEST
        if ($this->option('test') || $this->option('pest')) {
            $command = 'module:make:test ' . $this->module . ' Mail/' . $this->fullName;
            $command .= $this->option('pest') ? ' --pest' : null;
            Artisan::call($command, [], $this->getOutput());
        }
    }
}
