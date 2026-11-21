<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\State;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Pharaonic\Laravel\Modulator\Actions\EnableModule;
use Pharaonic\Laravel\Modulator\Concerns\ReportsStateChanges;
use Pharaonic\Laravel\Modulator\Concerns\ResolvesModules;
use RuntimeException;

final class EnableCommand extends Command
{
    use ConfirmableTrait;
    use ReportsStateChanges;
    use ResolvesModules;

    /**
     * @var string
     */
    protected $signature = 'module:enable
                            {module? : The module identity, e.g. Shop or Commerce/Checkout}
                            {--with-parents : Also enable its disabled parent modules}
                            {--with-dependencies : Also enable the disabled modules it depends on}
                            {--force : Run in production without asking}';

    /**
     * @var string
     */
    protected $description = 'Enable a module (removes it from pharaonic.modulator.disabled)';

    public function handle(EnableModule $enable): int
    {
        $module = $this->resolveModule($this->argument('module'));

        if ($module === null || ! $this->confirmToProceed()) {
            return 1;
        }

        try {
            $result = $enable->handle($module->id(), (bool) $this->option('with-parents'), (bool) $this->option('with-dependencies'));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        if ($result['removed'] === []) {
            $this->info(sprintf('Module [%s] is already enabled.', $module->id()));

            return 0;
        }

        $this->info(sprintf('Enabled [%s].', implode('], [', $result['removed'])));

        if ($result['also'] !== []) {
            $this->line(sprintf('Also enabled through it: [%s].', implode('], [', $result['also'])));
        }

        $this->reportStateChange($result['published']);

        return 0;
    }
}
