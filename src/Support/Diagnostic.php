<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Support;

/**
 * One discovery, selection or autoload finding.
 *
 * @internal
 */
final class Diagnostic
{
    public const ERROR = 'error';

    public const WARNING = 'warning';

    /**
     * @param  self::ERROR|self::WARNING  $level
     */
    public function __construct(
        public string $level,
        public string $message,
        public ?string $module = null
    ) {}

    public static function error(string $message, ?string $module = null): self
    {
        return new self(self::ERROR, $message, $module);
    }

    public static function warning(string $message, ?string $module = null): self
    {
        return new self(self::WARNING, $message, $module);
    }

    public function isError(): bool
    {
        return $this->level === self::ERROR;
    }

    /**
     * @return array{level: string, message: string, module: string|null}
     */
    public function toArray(): array
    {
        return ['level' => $this->level, 'message' => $this->message, 'module' => $this->module];
    }

    /**
     * @param  array{level: string, message: string, module: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['level'] === self::ERROR ? self::ERROR : self::WARNING, $data['message'], $data['module']);
    }
}
