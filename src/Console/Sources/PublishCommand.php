<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Sources;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;
use Pharaonic\Laravel\Modulator\Actions\PublishModule;
use Pharaonic\Laravel\Modulator\Concerns\AdvisesRestart;
use Pharaonic\Laravel\Modulator\Discovery\Manifest;
use Pharaonic\Laravel\Modulator\ModuleRegistry;
use Pharaonic\Laravel\Modulator\Support\Identity;
use RuntimeException;

final class PublishCommand extends Command
{
    use AdvisesRestart;

    /**
     * @var string
     */
    protected $signature = 'module:publish
                            {module : The module identity, e.g. Shop or Commerce/Checkout}
                            {--with-children : Publish its child modules too}
                            {--dry-run : Show what would change without writing}
                            {--force : Replace a local copy that differs from the package}';

    /**
     * @var string
     */
    protected $description = 'Copy a package-provided module into the local pool, where it overrides the package copy';

    public function handle(Manifest $manifest, Repository $config, PublishModule $publisher): int
    {
        $id = $this->argument('module');

        try {
            $id = Identity::assert(is_string($id) ? $id : '');
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        $data = $manifest->load();
        $registry = ModuleRegistry::fromManifest($data, $config->get('pharaonic.modulator.disabled', []));
        $poolName = $config->get('pharaonic.modulator.generation.pool', 'local');
        $target = is_string($poolName) ? $manifest->pool($poolName) : null;

        if ($target === null) {
            $this->error(sprintf('The generation pool [%s] (pharaonic.modulator.generation.pool) is not a configured pool.', is_scalar($poolName) ? (string) $poolName : ''));

            return 1;
        }

        $withChildren = (bool) $this->option('with-children');

        try {
            $plan = $publisher->plan($registry, $id, $target, $data['pools'], $withChildren);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        $this->line(sprintf('Module:  %s', $id));
        $this->line(sprintf('From:    %s (%s)', $plan['source']->path, (string) $plan['source']->package));
        $this->line(sprintf('To:      %s', $plan['target']));

        $changes = count($plan['added']) + count($plan['changed']) + count($plan['removed']);

        if ($plan['exists'] && $changes === 0) {
            $this->info('The local copy already matches the package.');

            return 0;
        }

        if ($plan['exists']) {
            $this->line(sprintf('Changes: %d added, %d changed, %d removed (relative to the local copy).', count($plan['added']), count($plan['changed']), count($plan['removed'])));

            foreach (array_slice(array_merge(
                array_map(static function (string $file): string {
                    return '+ '.$file;
                }, $plan['added']),
                array_map(static function (string $file): string {
                    return '~ '.$file;
                }, $plan['changed']),
                array_map(static function (string $file): string {
                    return '- '.$file;
                }, $plan['removed'])
            ), 0, 50) as $line) {
                $this->line('  '.$line);
            }
        } else {
            $this->line(sprintf('Files:   %d', count($plan['added'])));
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run: nothing was written.');

            return 0;
        }

        if ($plan['exists'] && ! $this->option('force')) {
            $this->error('A local copy exists and differs from the package. Review the changes above, then run again with --force (the local copy is kept as a backup).');

            return 1;
        }

        try {
            $backup = $publisher->publish($plan, $withChildren);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        if ($backup !== null) {
            $this->line('Backup:  '.$backup);
        }

        try {
            $manifest->rebuild();
        } catch (RuntimeException $e) {
            $this->warn('Published, but the module manifest could not be rebuilt: '.$e->getMessage().' Run module:discover.');
        }

        $this->info(sprintf('Module [%s] published. The local copy now overrides the package copy.', $id));
        $this->adviseRestart();

        return 0;
    }
}
