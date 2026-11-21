<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Actions;

use FilesystemIterator;
use Pharaonic\Laravel\Modulator\Module;
use Pharaonic\Laravel\Modulator\ModuleRegistry;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Static analysis of the class references between modules.
 *
 * It reads the PHP files of each module (not its tests or child modules) and
 * collects every class name it names: imports, fully qualified and qualified
 * names. A reference to another module must follow these rules:
 *
 * 1. the other module is declared in module.php "dependencies", or is an
 *    ancestor (a child always runs with its parent);
 * 2. a parent does not use its own child modules (they can be disabled alone);
 * 3. when the other module declares "exposes", only classes under those
 *    namespaces may be used.
 *
 * Names built at runtime (strings, container keys, dynamic calls) are not seen.
 *
 * @internal
 */
final class CheckBoundaries
{
    /**
     * @return list<array{module: string, file: string, line: int, class: string, message: string}>
     */
    public function handle(ModuleRegistry $registry, ?string $only = null): array
    {
        $modules = $registry->all();
        $violations = [];

        foreach ($modules as $id => $module) {
            if ($only !== null && $id !== $only) {
                continue;
            }

            foreach ($this->files($module) as $file) {
                foreach ($this->references($file) as $class => $line) {
                    $owner = $this->owner($class, $modules);

                    if ($owner === null || $owner === $id) {
                        continue;
                    }

                    $message = $this->violation($module, $modules[$owner], $class);

                    if ($message !== null) {
                        $violations[] = ['module' => $id, 'file' => $file, 'line' => $line, 'class' => $class, 'message' => $message];
                    }
                }
            }
        }

        return $violations;
    }

    private function violation(Module $module, Module $other, string $class): ?string
    {
        $id = $module->id();
        $otherId = $other->id();

        if (str_starts_with($otherId, $id.'/')) {
            return sprintf('[%s] uses [%s] of its child module [%s]. A parent must not depend on its children: they can be disabled on their own.', $id, $class, $otherId);
        }

        $ancestor = str_starts_with($id, $otherId.'/');

        if (! $ancestor && ! in_array($otherId, $module->dependencies(), true)) {
            return sprintf('[%s] uses [%s] of module [%s] without declaring it. Add "%s" to "dependencies" in its module.php.', $id, $class, $otherId, $otherId);
        }

        $exposes = $other->source()->exposes;

        if ($exposes === []) {
            return null;
        }

        foreach ($exposes as $namespace) {
            if (str_starts_with($class, $other->namespace().'\\'.$namespace.'\\')) {
                return null;
            }
        }

        return sprintf('[%s] uses [%s], which is internal to [%s]. It exposes only [%s].', $id, $class, $otherId, implode('], [', array_map(static function (string $namespace) use ($other): string {
            return $other->namespace().'\\'.$namespace;
        }, $exposes)));
    }

    /**
     * The module whose namespace holds the class (the most specific one).
     *
     * @param  array<string, Module>  $modules
     */
    private function owner(string $class, array $modules): ?string
    {
        $owner = null;
        $length = 0;

        foreach ($modules as $id => $module) {
            $namespace = $module->namespace().'\\';

            if (strlen($namespace) > $length && strncasecmp($class, $namespace, strlen($namespace)) === 0) {
                $owner = $id;
                $length = strlen($namespace);
            }
        }

        return $owner;
    }

    /**
     * The PHP files of a module, without tests/, vendor/ and child modules.
     *
     * @return list<string>
     */
    private function files(Module $module): array
    {
        $root = $module->path();

        if (! is_dir($root)) {
            return [];
        }

        $skip = ['Modules', 'tests', 'vendor', 'node_modules'];
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($root) + 1);
            $first = explode(DIRECTORY_SEPARATOR, $relative)[0];

            if (in_array($first, $skip, true) && $relative !== $first) {
                continue;
            }

            $files[] = $file->getPathname();
        }

        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * Class names a file refers to, with the first line of each.
     *
     * @return array<string, int>
     */
    public function references(string $file): array
    {
        $code = @file_get_contents($file);

        if ($code === false) {
            return [];
        }

        $tokens = token_get_all($code);
        $count = count($tokens);
        $namespace = '';
        $imports = [];
        $references = [];
        $depth = 0;
        $namespaceDepth = 0;

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (! is_array($token)) {
                if ($token === '{') {
                    $depth++;
                } elseif ($token === '}') {
                    $depth--;
                }

                continue;
            }

            [$kind, $text, $line] = $token;

            if ($kind === T_CURLY_OPEN || $kind === T_DOLLAR_OPEN_CURLY_BRACES) {
                $depth++;

                continue;
            }

            if ($kind === T_NAMESPACE) {
                [$namespace, $i] = $this->name($tokens, $i + 1);
                $next = $this->skip($tokens, $i);
                $namespaceDepth = $next !== null && $tokens[$next] === '{' ? 1 : 0;
                $imports = [];

                continue;
            }

            if ($kind === T_USE && $depth === $namespaceDepth) {
                $i = $this->imports($tokens, $i + 1, $imports, $references, $line);

                continue;
            }

            if ($kind === T_NAME_FULLY_QUALIFIED) {
                $references[ltrim($text, '\\')] ??= $line;
            } elseif ($kind === T_NAME_QUALIFIED) {
                $first = strtolower(explode('\\', $text)[0]);
                $resolved = isset($imports[$first])
                    ? $imports[$first].substr($text, (int) strpos($text, '\\'))
                    : ($namespace === '' ? $text : $namespace.'\\'.$text);
                $references[$resolved] ??= $line;
            } elseif ($kind === T_NAME_RELATIVE) {
                $references[$namespace.substr($text, strlen('namespace'))] ??= $line;
            }
        }

        return $references;
    }

    /**
     * Read a "use" statement at the top level; returns the index of its end.
     *
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
     * @param  array<string, string>  $imports  Lower-cased alias => class.
     * @param  array<string, int>  $references
     */
    private function imports(array $tokens, int $i, array &$imports, array &$references, int $line): int
    {
        $start = $this->skip($tokens, $i - 1);

        // A closure's "use (...)" at the top level of a routes file.
        if ($start !== null && $tokens[$start] === '(') {
            return $i - 1;
        }

        if ($start !== null && is_array($tokens[$start]) && in_array($tokens[$start][0], [T_FUNCTION, T_CONST], true)) {
            for (; $i < count($tokens) && $tokens[$i] !== ';'; $i++);

            return $i;
        }

        $prefix = '';
        $name = '';
        $alias = null;

        for (; $i < count($tokens); $i++) {
            $token = $tokens[$i];

            if ($token === ';') {
                $this->import($prefix.$name, $alias, $imports, $references, $line);

                return $i;
            }

            if ($token === ',' || $token === '}') {
                $this->import($prefix.$name, $alias, $imports, $references, $line);
                $name = '';
                $alias = null;
                $prefix = $token === '}' ? '' : $prefix;
            } elseif ($token === '{') {
                $prefix = rtrim($name, '\\').'\\';
                $name = '';
            } elseif (is_array($token) && $token[0] === T_AS) {
                $next = $this->skip($tokens, $i);

                if ($next !== null && is_array($tokens[$next])) {
                    $alias = $tokens[$next][1];
                    $i = $next;
                }
            } elseif (is_array($token) && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NS_SEPARATOR], true)) {
                $name .= $token[1];
            } elseif (is_array($token) && in_array($token[0], [T_FUNCTION, T_CONST], true)) {
                // "use A\{function f}" entries are not classes: skip the name.
                $name = '';
                $next = $this->skip($tokens, $i);
                $i = $next ?? $i;
            }
        }

        return $i;
    }

    /**
     * @param  array<string, string>  $imports
     * @param  array<string, int>  $references
     */
    private function import(string $class, ?string $alias, array &$imports, array &$references, int $line): void
    {
        $class = ltrim($class, '\\');

        if ($class === '' || str_ends_with($class, '\\')) {
            return;
        }

        $imports[strtolower($alias ?? substr($class, (int) strrpos('\\'.$class, '\\')))] = $class;
        $references[$class] ??= $line;
    }

    /**
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
     * @return array{0: string, 1: int} The name and the index of its last token.
     */
    private function name(array $tokens, int $i): array
    {
        $name = '';
        $last = $i - 1;

        for (; $i < count($tokens); $i++) {
            $token = $tokens[$i];

            if (is_array($token) && $token[0] === T_WHITESPACE && $name === '') {
                continue;
            }

            if (! is_array($token) || ! in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                break;
            }

            $name .= $token[1];
            $last = $i;
        }

        return [$name, $last];
    }

    /**
     * The index of the next token that is not whitespace or a comment.
     *
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
     */
    private function skip(array $tokens, int $i): ?int
    {
        for ($i++; $i < count($tokens); $i++) {
            if (! is_array($tokens[$i]) || ! in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                return $i;
            }
        }

        return null;
    }
}
