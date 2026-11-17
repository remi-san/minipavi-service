<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Cli\Console;

/**
 * Console writing in memory: what a command wrote, read with output() and errors()
 */
final class MemoryConsole
{
    public readonly Console $console;

    private \SplTempFileObject $output;

    private \SplTempFileObject $errors;

    public function __construct()
    {
        $this->output = new \SplTempFileObject();
        $this->errors = new \SplTempFileObject();
        $this->console = new Console($this->output, $this->errors);
    }

    public function output(): string
    {
        return self::read($this->output);
    }

    public function errors(): string
    {
        return self::read($this->errors);
    }

    private static function read(\SplTempFileObject $file): string
    {
        $file->rewind();
        $text = '';
        while (!$file->eof()) {
            $text .= $file->fgets();
        }

        return $text;
    }
}
