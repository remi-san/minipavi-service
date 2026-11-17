<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Http;

use MiniPaviService\Http\Dispatcher;
use MiniPaviService\Tests\Support\HttpRequests;
use MiniPaviService\Tests\Support\MemoryLogger;
use MiniPaviService\Tests\Support\RecordingHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

final class DispatcherTest extends TestCase
{
    private Dispatcher $dispatcher;

    private MemoryLogger $logger;

    protected function setUp(): void
    {
        $this->logger = new MemoryLogger();
        $this->dispatcher = new Dispatcher(new RecordingHandler('gateway'), new RecordingHandler('web'), new RecordingHandler('browser'), $this->logger);
    }

    public function testGivesTheGatewaysRequestsToItsHandler(): void
    {
        self::assertSame('gateway', (string) $this->dispatcher->handle(HttpRequests::to('MiniPAVI', ['web' => 'true']))->getBody());
        self::assertSame([], $this->logger->records);
    }

    public function testGivesABrowserAskingWebTrueToTheWebHandler(): void
    {
        self::assertSame('web', (string) $this->dispatcher->handle(HttpRequests::to('Mozilla/5.0', ['web' => 'true', 'route' => 'games']))->getBody());
        self::assertSame([[LogLevel::INFO, 'Screen shown in a browser', ['route' => 'games']]], $this->logger->records);
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function otherQueries(): iterable
    {
        yield 'no web' => [[]];
        yield 'web=1' => [['web' => '1']];
        yield 'web=yes' => [['web' => 'yes']];
        yield 'web=TRUE' => [['web' => 'TRUE']];
    }

    /**
     * @param array<string, string> $query
     */
    #[DataProvider('otherQueries')]
    public function testGivesOtherBrowsersToTheBrowserHandler(array $query): void
    {
        self::assertSame('browser', (string) $this->dispatcher->handle(HttpRequests::to('Mozilla/5.0', $query))->getBody());
    }
}
