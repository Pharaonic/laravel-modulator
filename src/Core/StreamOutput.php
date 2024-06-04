<?php

namespace Pharaonic\Laravel\Modulator\Core;

use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\StreamOutput as OutputStreamOutput;

class StreamOutput extends OutputStreamOutput
{
    private $condition;

    /**
     * @param resource                      $stream    A stream resource
     * @param int                           $verbosity The verbosity level (one of the VERBOSITY constants in OutputInterface)
     * @param bool|null                     $decorated Whether to decorate messages (null for auto-guessing)
     * @param OutputFormatterInterface|null $formatter Output formatter instance (null to use default OutputFormatter)
     *
     * @throws InvalidArgumentException When first argument is not a real stream
     */
    public function __construct($stream, callable $condition = null, int $verbosity = self::VERBOSITY_NORMAL, bool $decorated = null, OutputFormatterInterface $formatter = null)
    {
        parent::__construct($stream, $verbosity,  $decorated, $formatter);
      
        if ($condition) $this->condition = $condition;
    }

    /**
     * {@inheritdoc}
     */
    protected function doWrite(string $message, bool $newline): void
    {
        if ($this->condition && !(call_user_func($this->condition, $message))) return;

        parent::doWrite($message, $newline);
    }
}
