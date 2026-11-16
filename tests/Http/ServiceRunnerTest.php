<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Http;

use MiniPaviService\Core\Router;
use MiniPaviService\Core\Service;
use MiniPaviService\Gateway\MiniPaviClient;
use MiniPaviService\Http\EmulatorConfig;
use MiniPaviService\Http\RequestUrl;
use MiniPaviService\Http\ServiceRunner;
use MiniPaviService\Tests\Support\MemoryLogger;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

final class ServiceRunnerTest extends TestCase
{
    /**
     * In a separate process: if the service ran, MiniPaviCli::start() would exit
     */
    #[RunInSeparateProcess]
    public function testSendsBrowsersToTheEmulatorAndReturns(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';
        $_SERVER['HTTP_HOST'] = 'example.org';
        $_SERVER['REQUEST_URI'] = '/Svc/';
        $logger = new MemoryLogger();
        $runner = new ServiceRunner(
            new Service(new MiniPaviClient(new RequestUrl()), new Router()),
            new EmulatorConfig('http://emulator', 'ws://gateway'),
            new RequestUrl(),
            $logger,
        );

        $runner->run();

        $url = 'http://emulator/index.php?gw=' . urlencode('ws://gateway/?url=' . urlencode('http://example.org/Svc/'));
        self::assertSame([[LogLevel::INFO, 'Browser sent to the emulator', ['url' => $url]]], $logger->records);
    }
}
