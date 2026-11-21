<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Support;

use RuntimeException;

/**
 * The `disabled` list of config/pharaonic/modulator.php, as written in the
 * file (the package default while the application has not published it).
 *
 * @internal
 */
final class DisabledList
{
    /**
     * @param  string  $file  config/pharaonic/modulator.php of the application.
     * @param  string  $storage  The application storage directory (for the lock).
     */
    public function __construct(private string $file, private string $storage) {}

    public function file(): string
    {
        return $this->file;
    }

    /**
     * @return list<string>
     *
     * @throws RuntimeException
     */
    public function read(): array
    {
        return (new ConfigEditor)->read($this->code());
    }

    /**
     * Apply $edit under a lock and write the file atomically, publishing it
     * first when the application has none.
     *
     * @param  callable(ConfigEditor, string): string  $edit
     * @return bool Whether the file was published by this edit.
     *
     * @throws RuntimeException
     */
    public function edit(callable $edit): bool
    {
        return Files::locked($this->storage, 'config', function () use ($edit): bool {
            $published = is_file($this->file);
            $code = $this->code();
            $edited = $edit(new ConfigEditor, $code);

            if ($edited === $code && $published) {
                return false;
            }

            if ($published && ! is_writable($this->file)) {
                throw new RuntimeException(sprintf('[%s] is not writable. Change the [disabled] list in source control and deploy it.', $this->file));
            }

            Files::put($this->file, $edited);

            return ! $published;
        });
    }

    /**
     * @throws RuntimeException
     */
    private function code(): string
    {
        $code = @file_get_contents(is_file($this->file) ? $this->file : dirname(__DIR__, 2).'/config/modulator.php');

        if ($code === false) {
            throw new RuntimeException(sprintf('Cannot read [%s].', $this->file));
        }

        return $code;
    }
}
