<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Log\LogLines;

/**
 * Log lines kept in memory, in $lines
 */
final class MemoryLogLines implements LogLines
{
    /** @var list<string> */
    public array $lines = [];

    public function write(string $line): void
    {
        $this->lines[] = $line;
    }
}
