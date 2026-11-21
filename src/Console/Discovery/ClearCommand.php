<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Discovery;

use Illuminate\Console\Command;
use Pharaonic\Laravel\Modulator\Discovery\Manifest;

final class ClearCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'module:clear';

    /**
     * @var string
     */
    protected $description = 'Remove the module manifest';

    public function handle(Manifest $manifest): int
    {
        $this->info($manifest->clear() ? 'Module manifest removed.' : 'No module manifest to remove.');

        return 0;
    }
}
