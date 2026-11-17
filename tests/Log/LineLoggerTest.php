<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Log;

use MiniPaviService\Log\LineLogger;
use MiniPaviService\Tests\Support\MemoryLogLines;
use PHPUnit\Framework\TestCase;

final class LineLoggerTest extends TestCase
{
    private MemoryLogLines $lines;

    private LineLogger $logger;

    protected function setUp(): void
    {
        $this->lines = new MemoryLogLines();
        $this->logger = new LineLogger($this->lines);
    }

    public function testWritesTheLevelMessageAndContext(): void
    {
        $this->logger->info('Connection', ['fctn' => 'CNX']);

        self::assertSame(['[info] Connection {"fctn":"CNX"}'], $this->lines->lines);
    }

    public function testWritesTheExceptionWithItsTrace(): void
    {
        $exception = new \RuntimeException('directory.xml line 2: Premature end of data');

        $this->logger->error('Error serving the request', ['exception' => $exception, 'route' => 'directory']);

        [$line] = $this->lines->lines;
        self::assertStringStartsWith(
            "[error] Error serving the request {\"route\":\"directory\"}\nRuntimeException: directory.xml line 2: Premature end of data in "
            . __FILE__,
            $line,
        );
        self::assertStringContainsString('#0 ', $line);
    }

    public function testWithoutContextWritesTheMessageOnly(): void
    {
        $this->logger->warning('Unknown route');

        self::assertSame(['[warning] Unknown route'], $this->lines->lines);
    }

    public function testWritesStringableMessages(): void
    {
        $this->logger->notice(new class implements \Stringable {
            public function __toString(): string
            {
                return 'Disconnection';
            }
        });

        self::assertSame(['[notice] Disconnection'], $this->lines->lines);
    }
}
