<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Support;

use Filament\Forms\Commands\Concerns\CanGenerateForms;
use Filament\Support\Commands\Concerns\CanIndentStrings;
use Filament\Support\Commands\Concerns\CanReadModelSchemas;
use Filament\Tables\Commands\Concerns\CanGenerateTables;

/**
 * Filament's own form and table generation (make:filament-resource
 * --generate), for a model of a module. Loaded only when Filament is
 * installed and --generate is used.
 *
 * @internal
 */
final class FilamentSchema
{
    use CanGenerateForms;
    use CanGenerateTables;
    use CanIndentStrings;
    use CanReadModelSchemas;

    /**
     * @param  class-string  $model
     */
    public function form(string $model): string
    {
        return $this->getResourceFormSchema($model);
    }

    /**
     * @param  class-string  $model
     */
    public function table(string $model): string
    {
        return $this->getResourceTableColumns($model);
    }
}
