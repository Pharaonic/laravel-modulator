<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Discovery;

use FilesystemIterator;
use Pharaonic\Laravel\Modulator\Support\Diagnostic;
use Pharaonic\Laravel\Modulator\Support\Files;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Builds the class map of a module source from its PSR-4 directories.
 *
 * Every class name is derived from the file path (PSR-4) and checked against
 * the namespace and class the file declares, so a file that does not follow
 * PSR-4 is reported instead of mapped.
 *
 * @internal
 */
final class ClassMapBuilder
{
    /**
     * @param  list<string>  $exclude  Absolute directories to skip (child modules nested under app/).
     * @return array{classes: array<string, string>, diagnostics: list<Diagnostic>}
     */
    public function build(Source $source, array $exclude = []): array
    {
        $classes = [];
        $diagnostics = [];

        foreach ($source->prefixes() as $prefix => $directory) {
            if ($prefix === $source->namespace.'\\Tests\\' || ! is_dir($directory)) {
                continue;
            }

            foreach ($this->files($directory, $exclude) as $relative => $file) {
                $class = $prefix.str_replace('/', '\\', substr($relative, 0, -4));

                if (! $this->isValidClassName($class)) {
                    continue;
                }

                $declared = $this->declaredClass($file);

                if ($declared === null) {
                    continue;
                }

                if (strcasecmp($declared, $class) !== 0) {
                    $diagnostics[] = Diagnostic::warning(sprintf(
                        'File [%s] declares [%s] but its path maps to [%s] (PSR-4). It is not autoloaded.',
                        $file,
                        $declared,
                        $class
                    ), $source->id);

                    continue;
                }

                // A more specific prefix (Database\Factories) wins over the module root.
                $classes[$declared] = $classes[$declared] ?? $file;
            }
        }

        ksort($classes, SORT_STRING);

        return ['classes' => $classes, 'diagnostics' => $diagnostics];
    }

    /**
     * @param  list<string>  $exclude
     * @return array<string, string> Relative path (with "/") => absolute path.
     */
    private function files(string $directory, array $exclude): array
    {
        $files = [];
        $directory = Files::normalize($directory);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();

            foreach ($exclude as $excluded) {
                if (Files::within($path, $excluded)) {
                    continue 2;
                }
            }

            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($directory) + 1));
            $files[$relative] = $path;
        }

        ksort($files, SORT_STRING);

        return $files;
    }

    private function isValidClassName(string $class): bool
    {
        return preg_match('/^[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*(\\\\[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)*$/', $class) === 1;
    }

    /**
     * The first class, interface, trait or enum a file declares, fully qualified.
     */
    private function declaredClass(string $file): ?string
    {
        $code = @file_get_contents($file);

        if ($code === false) {
            return null;
        }

        $tokens = token_get_all($code);
        $namespace = '';
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (! is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = '';

                for ($j = $i + 1; $j < $count; $j++) {
                    $next = $tokens[$j];

                    if (is_array($next) && in_array($next[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                        $namespace .= $next[1];
                    } elseif (! is_array($next) || $next[0] !== T_WHITESPACE) {
                        break;
                    }
                }

                continue;
            }

            if (! $this->isClassKeyword($token) || $this->isClassReference($tokens, $i)) {
                continue;
            }

            $name = $this->nextName($tokens, $i);

            if ($name !== null) {
                return ltrim($namespace.'\\'.$name, '\\');
            }
        }

        return null;
    }

    /**
     * @param  array{0: int, 1: string, 2: int}  $token
     */
    private function isClassKeyword(array $token): bool
    {
        if (in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT], true)) {
            return true;
        }

        // PHP 8.1 has T_ENUM; on PHP 8.0 "enum" is a plain T_STRING.
        if (defined('T_ENUM') && $token[0] === constant('T_ENUM')) {
            return true;
        }

        return $token[0] === T_STRING && strtolower($token[1]) === 'enum';
    }

    /**
     * "Foo::class" and "new class" are not declarations.
     *
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
     */
    private function isClassReference(array $tokens, int $index): bool
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            $token = $tokens[$i];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return is_array($token) && in_array($token[0], [T_DOUBLE_COLON, T_NEW], true);
        }

        return false;
    }

    /**
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
     */
    private function nextName(array $tokens, int $index): ?string
    {
        $count = count($tokens);

        for ($i = $index + 1; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_array($token) && $token[0] === T_WHITESPACE) {
                continue;
            }

            return is_array($token) && $token[0] === T_STRING ? $token[1] : null;
        }

        return null;
    }
}
