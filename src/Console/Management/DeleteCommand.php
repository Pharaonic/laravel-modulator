<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Management;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Pharaonic\Laravel\Modulator\Actions\DeleteModule;
use Pharaonic\Laravel\Modulator\Concerns\AdvisesRestart;
use Pharaonic\Laravel\Modulator\Support\Identity;
use RuntimeException;

final class DeleteCommand extends Command
{
    use AdvisesRestart;

    /**
     * @var string
     */
    protected $signature = 'module:delete
                            {module : The module identity, e.g. Shop or Commerce/Checkout}
                            {--with-children : Also delete the child modules inside it}
                            {--dry-run : Show what would be deleted without deleting}
                            {--force : Delete without asking (for non-interactive, approved runs)}';

    /**
     * @var string
     */
    protected $description = 'Delete an application module directory';

    public function handle(DeleteModule $delete): int
    {
        $id = $this->argument('module');

        try {
            $plan = $delete->plan(Identity::assert(is_string($id) ? $id : ''), (bool) $this->option('with-children'));
        } catch (InvalidArgumentException|RuntimeException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        $this->line(sprintf('Module:   %s', $plan['id']));
        $this->line(sprintf('Path:     %s', $plan['path']));

        if ($plan['children'] !== []) {
            $this->line(sprintf('Children: %s', implode(', ', $plan['children'])));
        }

        if ($plan['replaced']) {
            $this->line('Another source provides this module; it becomes active after the deletion.');
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run: nothing was deleted.');

            return 0;
        }

        if (! $this->confirmed($plan['path'])) {
            return 1;
        }

        try {
            $delete->handle($plan);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        $this->info(sprintf('Module [%s] deleted.', $plan['id']));
        $this->adviseRestart();

        if ($plan['disabled']) {
            $this->line(sprintf('[%s] is still listed in pharaonic.modulator.disabled; remove it from config/pharaonic/modulator.php.', $plan['id']));
        }

        return 0;
    }

    private function confirmed(string $path): bool
    {
        if ($this->option('force')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            $this->error('Nothing was deleted. Confirm interactively, or add --force.');

            return false;
        }

        if ($this->confirm(sprintf('Delete [%s] and everything in it?', $path))) {
            return true;
        }

        $this->line('Nothing was deleted.');

        return false;
    }
}
