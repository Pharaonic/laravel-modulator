<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Support;

use ParseError;
use RuntimeException;

/**
 * Edits the `disabled` list of a configuration file in place.
 *
 * Only that list is touched: every other expression, comment and the layout
 * of the file stay as they are. The list must be a literal array of string
 * literals (comments allowed); anything computed (env(), explode(), a
 * variable...) is refused, since rewriting it could change its meaning.
 *
 * @internal
 */
final class ConfigEditor
{
    private const KEY = 'disabled';

    /**
     * The identities listed under `disabled`.
     *
     * @return list<string>
     *
     * @throws RuntimeException When the file cannot be edited safely.
     */
    public function read(string $code): array
    {
        $list = $this->locate($code);

        return $list === null ? [] : array_column($list['items'], 'value');
    }

    /**
     * Add an identity to `disabled` (no change when it is already listed).
     *
     * @throws RuntimeException
     */
    public function add(string $code, string $id): string
    {
        $list = $this->locate($code);

        if ($list === null) {
            $edited = $this->insertKey($code, $id);
        } elseif (in_array($id, array_column($list['items'], 'value'), true)) {
            return $code;
        } else {
            $edited = $this->append($code, $list, $id);
        }

        return $this->verify($edited, array_merge($this->read($code), [$id]));
    }

    /**
     * Remove an identity from `disabled` (every occurrence).
     *
     * @throws RuntimeException
     */
    public function remove(string $code, string $id): string
    {
        $list = $this->locate($code);

        if ($list === null || ! in_array($id, array_column($list['items'], 'value'), true)) {
            return $code;
        }

        $edited = $code;

        // Remove from the end, so earlier offsets stay valid.
        foreach (array_reverse($list['items']) as $item) {
            if ($item['value'] === $id) {
                $edited = $this->removeItem($edited, $item, $list);
            }
        }

        $expected = array_values(array_filter($this->read($code), static function (string $value) use ($id): bool {
            return $value !== $id;
        }));

        return $this->verify($edited, $expected);
    }

    /**
     * @return array{open: int, close: int, inline: bool, key: int, items: list<array{value: string, start: int, end: int, comma: int|null}>}|null
     *
     * @throws RuntimeException
     */
    private function locate(string $code): ?array
    {
        try {
            $tokens = token_get_all($code, TOKEN_PARSE);
        } catch (ParseError $e) {
            throw new RuntimeException('The configuration file has a syntax error: '.$e->getMessage());
        }

        $offsets = [];
        $offset = 0;

        foreach ($tokens as $i => $token) {
            $offsets[$i] = $offset;
            $offset += strlen(is_array($token) ? $token[1] : $token);
        }

        $count = count($tokens);
        $return = null;

        for ($i = 0; $i < $count; $i++) {
            if (is_array($tokens[$i]) && $tokens[$i][0] === T_RETURN) {
                $return = $i;

                break;
            }
        }

        if ($return === null) {
            throw new RuntimeException('The configuration file does not return an array.');
        }

        $open = $this->arrayStart($tokens, $this->next($tokens, $return));

        if ($open === null) {
            throw new RuntimeException('The configuration file does not return a literal array.');
        }

        $close = $this->matching($tokens, $open);
        $found = null;
        $depth = 0;

        for ($i = $open + 1; $i < $close; $i++) {
            $token = $tokens[$i];
            $text = is_array($token) ? $token[1] : $token;

            if (in_array($text, ['[', '(', '{'], true) || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $depth++;

                continue;
            }

            if (in_array($text, [']', ')', '}'], true)) {
                $depth--;

                continue;
            }

            if ($depth !== 0 || ! is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING || $this->unquote($token[1]) !== self::KEY) {
                continue;
            }

            $arrow = $this->next($tokens, $i);

            if ($arrow === null || ! is_array($tokens[$arrow]) || $tokens[$arrow][0] !== T_DOUBLE_ARROW) {
                continue;
            }

            if ($found !== null) {
                throw new RuntimeException('The configuration file declares the [disabled] key twice. Keep one of them.');
            }

            $found = [$i, $this->next($tokens, $arrow)];
        }

        if ($found === null) {
            return null;
        }

        [$key, $value] = $found;
        $listOpen = $this->arrayStart($tokens, $value);

        if ($listOpen === null) {
            throw $this->computed();
        }

        $listClose = $this->matching($tokens, $listOpen);
        $items = [];
        $current = null;

        for ($i = $listOpen + 1; $i < $listClose; $i++) {
            $token = $tokens[$i];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            if ($token === ',') {
                if ($current === null) {
                    throw $this->computed();
                }

                $current['comma'] = $offsets[$i];
                $items[] = $current;
                $current = null;

                continue;
            }

            // Only string literals, each followed by a comma except the last.
            if (! is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING || $current !== null) {
                throw $this->computed();
            }

            $current = ['value' => $this->unquote($token[1]), 'start' => $offsets[$i], 'end' => $offsets[$i] + strlen($token[1]), 'comma' => null];
        }

        if ($current !== null) {
            $items[] = $current;
        }

        $openOffset = $offsets[$listOpen] + 1;

        return [
            'open' => $openOffset,
            'close' => $offsets[$listClose],
            'inline' => ! str_contains(substr($code, $openOffset, $offsets[$listClose] - $openOffset), "\n"),
            'key' => $offsets[$key],
            'items' => $items,
        ];
    }

    /**
     * @param  array{open: int, close: int, inline: bool, key: int, items: list<array{value: string, start: int, end: int, comma: int|null}>}  $list
     */
    private function append(string $code, array $list, string $id): string
    {
        $literal = $this->quote($id);
        $last = $list['items'] === [] ? null : $list['items'][count($list['items']) - 1];
        $comma = $last !== null && $last['comma'] === null;

        if ($list['inline']) {
            $insert = ($last === null ? '' : ($comma ? ', ' : ' ')).$literal;
            $at = $last === null ? $list['open'] : ($last['comma'] ?? $last['end']) + ($comma ? 0 : 1);

            return substr($code, 0, $at).$insert.substr($code, $at);
        }

        $lineStart = (int) strrpos(substr($code, 0, $list['close']), "\n") + 1;
        $beforeClose = substr($code, $lineStart, $list['close'] - $lineStart);
        $indent = $last !== null
            ? $this->indentation($code, $last['start'])
            : $this->indentation($code, $list['key']).'    ';
        $insert = $indent.$literal.','."\n";

        if (trim($beforeClose) === '') {
            $edited = substr($code, 0, $lineStart).$insert.substr($code, $lineStart);
        } else {
            $edited = substr($code, 0, $list['close'])."\n".$insert.$this->indentation($code, $list['key']).substr($code, $list['close']);
        }

        if ($comma) {
            $edited = substr($edited, 0, $last['end']).','.substr($edited, $last['end']);
        }

        return $edited;
    }

    /**
     * @param  array{value: string, start: int, end: int, comma: int|null}  $item
     * @param  array{open: int, close: int, inline: bool, key: int, items: list<array{value: string, start: int, end: int, comma: int|null}>}  $list
     */
    private function removeItem(string $code, array $item, array $list): string
    {
        $end = $item['comma'] !== null ? $item['comma'] + 1 : $item['end'];
        $lineStart = (int) strrpos(substr($code, 0, $item['start']), "\n") + 1;
        $lineEnd = strpos($code, "\n", $end);
        $lineEnd = $lineEnd === false ? strlen($code) : $lineEnd;

        // The entry has its own line: remove the line.
        if (! $list['inline'] && trim(substr($code, $lineStart, $item['start'] - $lineStart)) === '' && trim(substr($code, $end, $lineEnd - $end)) === '') {
            return substr($code, 0, $lineStart).substr($code, $lineEnd + 1);
        }

        $start = $item['start'];

        if ($item['comma'] === null) {
            // The last entry: drop the comma before it instead.
            $before = rtrim(substr($code, 0, $start));

            if (str_ends_with($before, ',')) {
                $start = strlen($before) - 1;
            }
        } else {
            while ($end < strlen($code) && $code[$end] === ' ') {
                $end++;
            }
        }

        return substr($code, 0, $start).substr($code, $end);
    }

    private function insertKey(string $code, string $id): string
    {
        $tokens = token_get_all($code);
        $offset = 0;
        $return = false;

        foreach ($tokens as $i => $token) {
            $text = is_array($token) ? $token[1] : $token;

            if (is_array($token) && $token[0] === T_RETURN) {
                $return = true;
            } elseif ($return && ($text === '[' || $text === '(')) {
                $at = $offset + strlen($text);

                return substr($code, 0, $at)."\n\n    '".self::KEY."' => [\n        ".$this->quote($id).",\n    ],\n".substr($code, $at);
            }

            $offset += strlen($text);
        }

        throw new RuntimeException('The configuration file does not return a literal array.');
    }

    /**
     * Re-read the edited code and make sure it says exactly what was intended.
     *
     * @param  list<string>  $expected
     *
     * @throws RuntimeException
     */
    private function verify(string $edited, array $expected): string
    {
        if ($this->read($edited) !== $expected) {
            throw new RuntimeException('The [disabled] list could not be edited safely; the file was not changed. Edit it by hand.');
        }

        return $edited;
    }

    private function computed(): RuntimeException
    {
        return new RuntimeException('The [disabled] list is not a plain list of strings (it is computed, e.g. with env() or a variable), so it cannot be edited safely. Edit it by hand or make it a literal list.');
    }

    /**
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
     */
    private function next(array $tokens, ?int $i): ?int
    {
        if ($i === null) {
            return null;
        }

        for ($i++; $i < count($tokens); $i++) {
            if (! is_array($tokens[$i]) || ! in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                return $i;
            }
        }

        return null;
    }

    /**
     * The index of the opening bracket of a literal array at $i ("[" or the
     * "(" of "array("), or null.
     *
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
     */
    private function arrayStart(array $tokens, ?int $i): ?int
    {
        if ($i === null) {
            return null;
        }

        if ($tokens[$i] === '[') {
            return $i;
        }

        if (is_array($tokens[$i]) && $tokens[$i][0] === T_ARRAY) {
            $next = $this->next($tokens, $i);

            return $next !== null && $tokens[$next] === '(' ? $next : null;
        }

        return null;
    }

    /**
     * The index of the bracket closing the array opened at $open.
     *
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
     *
     * @throws RuntimeException
     */
    private function matching(array $tokens, int $open): int
    {
        $depth = 0;

        for ($i = $open; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            $text = is_array($token) ? $token[1] : $token;

            if (in_array($text, ['[', '(', '{'], true) || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $depth++;
            } elseif (in_array($text, [']', ')', '}'], true)) {
                $depth--;

                if ($depth === 0) {
                    return $i;
                }
            }
        }

        throw new RuntimeException('The configuration file has an unclosed array.');
    }

    private function indentation(string $code, int $offset): string
    {
        $lineStart = (int) strrpos(substr($code, 0, $offset), "\n") + 1;
        $line = substr($code, $lineStart, $offset - $lineStart);

        return substr($line, 0, strlen($line) - strlen(ltrim($line, " \t")));
    }

    private function unquote(string $literal): string
    {
        $body = substr($literal, 1, -1);

        if ($literal[0] === "'") {
            return str_replace(['\\\\', "\\'"], ['\\', "'"], $body);
        }

        return stripcslashes($body);
    }

    private function quote(string $value): string
    {
        return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'";
    }
}
