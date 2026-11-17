<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Http;

use MiniPaviService\Http\Dispatcher;
use MiniPaviService\Http\EmulatorConfig;
use MiniPaviService\Http\RequestUrl;
use MiniPaviService\Tests\Support\MemoryLogger;
use MiniPaviService\Tests\Support\RecordingRunner;
use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

#[BackupGlobals(true)]
final class DispatcherTest extends TestCase
{
    private RecordingRunner $gateway;

    private RecordingRunner $web;

    private MemoryLogger $logger;

    protected function setUp(): void
    {
        $this->gateway = new RecordingRunner();
        $this->web = new RecordingRunner();
        $this->logger = new MemoryLogger();
        $_SERVER['HTTP_HOST'] = 'example.org';
    }

    public function testRunsTheServiceForTheGateway(): void
    {
        $this->dispatch('MiniPAVI', 'web=true');

        self::assertSame([1, 0], [$this->gateway->runs, $this->web->runs]);
        self::assertSame([], $this->logger->records);
    }

    public function testShowsTheScreenToABrowserAskingWebTrue(): void
    {
        $this->dispatch('Mozilla/5.0', 'web=true&route=games');

        self::assertSame([0, 1], [$this->gateway->runs, $this->web->runs]);
        self::assertSame([[LogLevel::INFO, 'Screen shown in a browser', ['route' => 'games']]], $this->logger->records);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function otherQueries(): iterable
    {
        yield 'no web' => [''];
        yield 'web=1' => ['web=1'];
        yield 'web=yes' => ['web=yes'];
        yield 'web=TRUE' => ['web=TRUE'];
    }

    #[DataProvider('otherQueries')]
    public function testSendsOtherBrowsersToTheEmulator(string $query): void
    {
        $this->dispatch('Mozilla/5.0', $query);

        self::assertSame([0, 0], [$this->gateway->runs, $this->web->runs]);
        $url = 'http://emulator/index.php?gw=' . urlencode('ws://gateway/?url=' . urlencode('http://example.org/Svc/' . ($query === '' ? '' : "?$query")));
        self::assertSame([[LogLevel::INFO, 'Browser sent to the emulator', ['url' => $url]]], $this->logger->records);
    }

    private function dispatch(string $userAgent, string $query): void
    {
        $_SERVER['HTTP_USER_AGENT'] = $userAgent;
        $_SERVER['QUERY_STRING'] = $query;
        $_SERVER['REQUEST_URI'] = '/Svc/' . ($query === '' ? '' : "?$query");

        (new Dispatcher($this->gateway, $this->web, new EmulatorConfig('http://emulator', 'ws://gateway'), new RequestUrl(), $this->logger))->run();
    }
}
