<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Pharaonic\Laravel\Modulator\Core\Command;

class MakeNotification extends Command
{
    protected $description = 'Create a new notification class of a module';
    protected $signature = 'module:make:notification {module : Module\'s name} {name : Notification\'s name}
                            {--test : Generate an accompanying PHPUnit test for the Notification}
                            {--pest : Generate an accompanying Pest test for the Notification}
                            {--f|force : Create the class even if the notification already exists}
                            {--m|markdown= : Create a new Markdown template for the notification}';

    public function exec()
    {
        if (!$this->moduleExists()) return;

        // CREATE NOTIFICATIONS DIRECTORY IF NOT FOUND
        if (!file_exists($notifications = $this->getPath('Notifications')))
            File::makeDirectory($notifications, 0777, true, true);

        $content = str_replace('{{ class }}', $this->name, file_get_contents(__DIR__ . '/stubs/notification.stub'));
        $content = str_replace('{{ namespace }}', $this->getNamespace('Notifications'), $content);

        if ($this->option('markdown'))
            $content = str_replace([
                PHP_EOL . "                    ->line('The introduction to the notification.')",
                PHP_EOL . "                    ->action('Notification Action', url('/'))",
                PHP_EOL . "                    ->line('Thank you for using our application!');"
            ], [
                '->markdown(\'' . $this->option('markdown') . '\');',
                '',
                ''
            ], $content);


        // SAVING NOTIFICATION
        if (file_exists($path = $this->getPath('Notifications/' . $this->fullName . '.php')) && !$this->option('force')) {
            $this->error('Notification is already exists!');
            return false;
        }

        if (!File::isDirectory($dir = dirname($path)))
            File::makeDirectory($dir, 0755, true, true);

        if (File::put($path, $content)) {
            $this->info('Notification created successfully.');
        } else {
            $this->warn('There is something wrong.');
        }


        // CREATE TEST
        if ($this->option('test') || $this->option('pest')) {
            $command = 'module:make:test ' . $this->module . ' Notifications/' . $this->fullName;
            $command .= $this->option('pest') ? ' --pest' : null;
            Artisan::call($command, [], $this->getOutput());
        }
    }
}
