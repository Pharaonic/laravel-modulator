<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Pharaonic\Laravel\Modulator\Core\Command;
use Symfony\Component\Process\Process;

class Test extends Command
{

    protected $description = 'Run the module tests';

    protected $signature = 'module:test {module}
                            {--l|list : Get tests list}
                            {--s|stop-on-failure : Stop all tests on the failure status}
                            {--f|filter= : Test name (example: UserTest)}';


    protected function exec()
    {
        if (!$this->moduleExists()) return;

        // COMMAND
        $command = 'php artisan test app/Modules/' . $this->module . '/tests';

        if ($this->option('list')) {
            $command .= ' --list-tests';
        } else {
            if ($filter = $this->option('filter')) $command .= ' --filter=' . $filter;
            if ($this->option('stop-on-failure')) $command .= ' --stop-on-failure';
        }

        $process = Process::fromShellCommandline($command);
        $process->setPty(true);
        $process->run();
    }
}
