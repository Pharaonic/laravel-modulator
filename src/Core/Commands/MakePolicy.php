<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Pharaonic\Laravel\Modulator\Core\Command;

class MakePolicy extends Command
{
    protected $description = 'Create a new policy class of a module';
    protected $signature = 'module:make:policy {module : Module\'s name} {name : Policy\'s name}
                            {--guard= : The guard that the policy relies on}
                            {--model= : The model that the policy applies to}';

    public function exec()
    {
        if (!$this->moduleExists()) return;

        // CREATE POLICIES DIRECTORY IF NOT FOUND
        if (!file_exists($policies = $this->getPath('Policies')))
            File::makeDirectory($policies, 0777, true, true);

        // POLICY NAME && GUARD
        $guard  = $this->option('guard') ?? config('auth.defaults.guard');

        if (!config('auth.guards.' . $guard)) {
            $this->error('Guard [ ' . $guard . ' ] has been not found.');
            return false;
        }

        if (!($provider = config('auth.guards.' . $guard . '.provider'))) {
            $this->error('Guard\'s Provider [ ' . $guard . ' ] has been not found.');
            return false;
        }

        if (!($model = config('auth.providers.' . $provider . '.model', $this->option('model')))) {
            $this->error('Guard\'s Provider -> Model [ ' . $guard . ' ] has been not found.');
            return false;
        }


        // GENERATE POLICY
        if (!$this->option('model'))
            $content = $this->getPlainPolicy($model);
        else
            $content = $this->getPolicy($model, $this->option('model'));

        // SAVING POLICY
        if (file_exists($path = $this->getPath('Policies/' . $this->fullName . '.php'))) {
            $this->error('Policy is already exists!');
            return false;
        }

        if (!File::isDirectory($dir = dirname($path)))
            File::makeDirectory($dir, 0755, true, true);

        if (File::put($path, $content)) {
            $this->info('Policy created successfully.');
        } else {
            $this->warn('There is something wrong.');
        }
    }

    protected function getPlainPolicy(string $model)
    {
        $stubContent = str_replace('{{ class }}', $this->name, file_get_contents(__DIR__ . '/stubs/policy.plain.stub'));
        $stubContent = str_replace('{{ namespace }}', $this->getNamespace('Policies'), $stubContent);
        $stubContent = str_replace('{{ namespacedUserModel }}', $model, $stubContent);

        return $stubContent;
    }

    protected function getPolicy(string $model, string $custom)
    {
        $stubContent = str_replace('{{ class }}', $this->name, file_get_contents(__DIR__ . '/stubs/policy.stub'));
        $stubContent = str_replace('{{ namespace }}', $this->getNamespace('Policies'), $stubContent);
        $stubContent = str_replace('{{ namespacedUserModel }}', $model, $stubContent);

        $user = explode('\\', $model);
        $user = $user[count($user) - 1];
        $stubContent = str_replace('{{ user }}', $user, $stubContent);

        if ($model == $custom) {
            $stubContent = str_replace('use {{ namespacedModel }};' . PHP_EOL, '', $stubContent);
            $stubContent = str_replace('{{ namespacedModel }}', $model, $stubContent);
            $stubContent = str_replace('{{ model }}', $user, $stubContent);
            $stubContent = str_replace('{{ modelVariable }}', 'model', $stubContent);
        } else {
            $stubContent = str_replace('{{ namespacedModel }}', $custom, $stubContent);

            $custom = explode('\\', $custom);
            $custom = $custom[count($custom) - 1];
            $stubContent = str_replace('{{ model }}', $custom, $stubContent);
            $stubContent = str_replace('{{ modelVariable }}', Str::camel($custom), $stubContent);
        }

        return $stubContent;
    }
}
