<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Http\Emulator;

use MiniPaviService\Http\Emulator\EmulatorConfig;
use MiniPaviService\Http\Emulator\EmulatorRedirect;
use MiniPaviService\Tests\Support\HttpRequests;
use MiniPaviService\Tests\Support\MemoryLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

final class EmulatorRedirectTest extends TestCase
{
    public function testSendsTheBrowserToTheEmulatorAtTheUrlItAskedFor(): void
    {
        $logger = new MemoryLogger();

        $response = (new EmulatorRedirect(new EmulatorConfig('http://emulator', 'ws://gateway'), $logger))->handle(HttpRequests::to('Mozilla/5.0', ['web' => '1']));

        $url = 'http://emulator/index.php?gw=' . urlencode('ws://gateway/?url=' . urlencode('http://services/Svc/?web=1'));
        self::assertSame(302, $response->getStatusCode());
        self::assertSame($url, $response->getHeaderLine('Location'));
        self::assertSame([[LogLevel::INFO, 'Browser sent to the emulator', ['url' => $url]]], $logger->records);
    }
}
