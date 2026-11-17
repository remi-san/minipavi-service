<?php

declare(strict_types=1);

namespace MiniPaviService\Cli;

/**
 * Where a command writes: its output (e.g. a screen, a file's content), and its errors
 */
final readonly class Console
{
    public function __construct(
        private \SplFileObject $output,
        private \SplFileObject $errors,
    ) {}

    /**
     * Console of the process: the standard output and the standard error
     */
    public static function standard(): self
    {
        return new self(new \SplFileObject('php://stdout', 'w'), new \SplFileObject('php://stderr', 'w'));
    }

    public function write(string $text): void
    {
        $this->output->fwrite($text);
    }

    public function error(string $text): void
    {
        $this->errors->fwrite($text);
    }
}
