<?php

declare(strict_types=1);

namespace MiniPaviService\Tests;

use MiniPaviService\ErrorLogLogger;
use PHPUnit\Framework\TestCase;

final class ErrorLogLoggerTest extends TestCase
{
    private string $file;

    private string|false $previous;

    protected function setUp(): void
    {
        $this->file = (string) tempnam(sys_get_temp_dir(), 'log');
        $this->previous = ini_set('error_log', $this->file);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', (string) $this->previous);
        @unlink($this->file);
    }

    public function testWritesTheLevelMessageAndContext(): void
    {
        (new ErrorLogLogger())->info('Connection', ['fctn' => 'CNX']);

        self::assertStringEndsWith("[info] Connection {\"fctn\":\"CNX\"}\n", (string) file_get_contents($this->file));
    }

    public function testWritesTheExceptionWithItsTrace(): void
    {
        $exception = new \RuntimeException('directory.xml line 2: Premature end of data');

        (new ErrorLogLogger())->error('Error serving the request', ['exception' => $exception, 'route' => 'directory']);

        $log = (string) file_get_contents($this->file);
        self::assertStringContainsString('[error] Error serving the request {"route":"directory"}', $log);
        self::assertStringContainsString('RuntimeException: directory.xml line 2: Premature end of data in ' . __FILE__, $log);
        self::assertStringContainsString('#0 ', $log);
    }

    public function testWithoutContextWritesTheMessageOnly(): void
    {
        (new ErrorLogLogger())->warning('Unknown route');

        self::assertStringEndsWith("[warning] Unknown route\n", (string) file_get_contents($this->file));
    }
}
