<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\State;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Pharaonic\Laravel\Modulator\Actions\DisableModule;
use Pharaonic\Laravel\Modulator\Concerns\ReportsStateChanges;
use Pharaonic\Laravel\Modulator\Concerns\ResolvesModules;
use RuntimeException;

final class DisableCommand extends Command
{
    use ConfirmableTrait;
    use ReportsStateChanges;
    use ResolvesModules;

    /**
     * @var string
     */
    protected $signature = 'module:disable
                            {module? : The module identity, e.g. Shop or Commerce/Checkout}
                            {--cascade : Also disable the modules that depend on it (they are listed first)}
                            {--force : Do not ask: in production, and to confirm --cascade}';

    /**
     * @var string
     */
    protected $description = 'Disable a module (adds it to pharaonic.modulator.disabled)';

    public function handle(DisableModule $disable): int
    {
        $module = $this->resolveModule($this->argument('module'));

        if ($module === null || ! $this->confirmToProceed()) {
            return 1;
        }

        $id = $module->id();

        try {
            $plan = $disable->plan($id);

            if ($plan['disabled']) {
                $this->info(sprintf('Module [%s] is already disabled.', $id));

                return 0;
            }

            if ($plan['dependents'] !== [] && ! $this->confirmCascade($id, $plan['dependents'])) {
                return 1;
            }

            $published = $disable->handle($id);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        $this->info(sprintf('Disabled [%s].', $id));

        if ($plan['children'] !== []) {
            $this->line(sprintf('Its child modules follow it and are disabled too (their own state is kept): [%s].', implode('], [', $plan['children'])));
        }

        $this->reportStateChange($published);

        return 0;
    }

    /**
     * @param  array<string, string>  $dependents
     */
    private function confirmCascade(string $id, array $dependents): bool
    {
        $this->warn(sprintf('Disabling [%s] also disables the modules that depend on it:', $id));

        foreach ($dependents as $dependent => $reason) {
            $this->line(sprintf('  %s (%s)', $dependent, $reason));
        }

        if (! $this->option('cascade')) {
            $this->error('Nothing was changed. Disable those modules first, or run again with --cascade.');

            return false;
        }

        if ($this->option('force') || ($this->input->isInteractive() && $this->confirm('Disable them all?'))) {
            return true;
        }

        $this->error('Nothing was changed. Confirm interactively, or add --force.');

        return false;
    }
}
