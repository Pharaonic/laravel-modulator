<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;
use Pharaonic\Laravel\Modulator\Module;
use RuntimeException;
use Symfony\Component\Console\Input\InputOption;

final class MailMakeCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:mail';

    /**
     * @var string
     */
    protected $description = 'Create a new email class in a module';

    protected string $directory = 'Mail';

    protected string $stub = 'mail.stub';

    protected bool $testable = true;

    protected function typeOptions(): array
    {
        return [
            ['markdown', 'm', InputOption::VALUE_OPTIONAL, 'Create a Markdown template for the mailable', false],
            ['view', null, InputOption::VALUE_OPTIONAL, 'Create a Blade template for the mailable', false],
        ];
    }

    protected function build(Module $module, string $class, array $segments, array $replace, Repository $config): array
    {
        $replace['subject'] = Str::title(Str::snake($class, ' '));
        $markdown = $this->option('markdown');
        $view = $this->option('view');

        if ($markdown === false && $view === false) {
            return [$this->stub, $replace, []];
        }

        $chosen = $markdown !== false ? $markdown : $view;
        $name = is_string($chosen) && $chosen !== '' ? $chosen : 'mail.'.Str::kebab($class);

        if (preg_match('/^[A-Za-z0-9_-]+(\.[A-Za-z0-9_-]+)*$/', $name) !== 1) {
            throw new RuntimeException(sprintf('[%s] is not a valid view name.', $name));
        }

        $replace['view'] = $module->slug().'::'.$name;
        $file = $module->path('resources/views/'.str_replace('.', '/', $name).'.blade.php');

        if ($markdown === false) {
            return ['mail.view.stub', $replace, [[$file, 'view-mail.stub']]];
        }

        // <x-mail::message> components arrived with Laravel 9.
        $template = class_exists('Illuminate\\Mail\\Mailables\\Content') ? 'markdown.stub' : 'markdown.legacy.stub';

        return ['mail.markdown.stub', $replace, [[$file, $template]]];
    }
}
