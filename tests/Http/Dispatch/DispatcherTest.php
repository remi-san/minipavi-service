<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Http\Dispatch;

use MiniPaviService\Http\Dispatch\Dispatcher;
use MiniPaviService\Http\Dispatch\FromMiniPaviGateway;
use MiniPaviService\Http\Dispatch\QueryParameterEquals;
use MiniPaviService\Http\Dispatch\Rule;
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
        // The services' rules
        $this->logger = new MemoryLogger();
        $this->dispatcher = new Dispatcher(new RecordingHandler('browser'), [
            new Rule('gateway', new FromMiniPaviGateway(), new RecordingHandler('gateway')),
            new Rule('web', new QueryParameterEquals('web', 'true'), new RecordingHandler('web')),
        ], $this->logger);
    }

    public function testGivesTheRequestToTheFirstRuleMatchingIt(): void
    {
        // The gateway's rule comes first, even with web=true
        self::assertSame('gateway', (string) $this->dispatcher->handle(HttpRequests::to('MiniPAVI', ['web' => 'true']))->getBody());
        self::assertSame([[LogLevel::DEBUG, 'Request dispatched', ['rule' => 'gateway']]], $this->logger->records);
    }

    public function testGivesABrowserAskingWebTrueToTheWebRule(): void
    {
        self::assertSame('web', (string) $this->dispatcher->handle(HttpRequests::to('Mozilla/5.0', ['web' => 'true', 'route' => 'games']))->getBody());
        self::assertSame([[LogLevel::DEBUG, 'Request dispatched', ['rule' => 'web']]], $this->logger->records);
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
    public function testGivesWhatNoRuleMatchesToTheFallback(array $query): void
    {
        self::assertSame('browser', (string) $this->dispatcher->handle(HttpRequests::to('Mozilla/5.0', $query))->getBody());
        self::assertSame([[LogLevel::DEBUG, 'Request dispatched', ['rule' => 'fallback']]], $this->logger->records);
    }

    public function testAServiceAddsItsOwnRules(): void
    {
        // E.g. a health check, before the others
        $dispatcher = new Dispatcher(new RecordingHandler('browser'), [
            new Rule('health', new QueryParameterEquals('health', '1'), new RecordingHandler('ok')),
            new Rule('web', new QueryParameterEquals('web', 'true'), new RecordingHandler('web')),
        ]);

        self::assertSame('ok', (string) $dispatcher->handle(HttpRequests::to('Mozilla/5.0', ['health' => '1', 'web' => 'true']))->getBody());
    }

    public function testWithoutRulesEverythingGoesToTheFallback(): void
    {
        self::assertSame('browser', (string) (new Dispatcher(new RecordingHandler('browser'), []))->handle(HttpRequests::to('MiniPAVI'))->getBody());
    }
}
