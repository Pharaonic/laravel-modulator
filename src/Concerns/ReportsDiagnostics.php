<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Concerns;

use Pharaonic\Laravel\Modulator\Support\Diagnostic;

/**
 * @internal
 */
trait ReportsDiagnostics
{
    /**
     * Print the diagnostics and return how many are errors.
     *
     * @param  list<Diagnostic>  $diagnostics
     */
    protected function reportDiagnostics(array $diagnostics): int
    {
        $errors = 0;

        foreach ($diagnostics as $diagnostic) {
            if ($diagnostic->isError()) {
                $errors++;
                $this->error('  ERROR  '.$diagnostic->message);
            } else {
                $this->warn('  WARN   '.$diagnostic->message);
            }
        }

        return $errors;
    }
}
