<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use Psr\Log\AbstractLogger;

/**
 * Logger keeping its records, to check what was logged
 */
final class MemoryLogger extends AbstractLogger
{
    /** @var list<array{mixed, string, array<mixed>}> level, message and context of each record */
    public array $records = [];

    /**
     * @param array<mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [$level, (string) $message, $context];
    }
}
