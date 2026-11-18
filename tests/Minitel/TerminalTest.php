<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Minitel;

use MiniPaviService\Minitel\Terminal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TerminalTest extends TestCase
{
    /**
     * @return iterable<string, array{Terminal, bool, bool}>
     */
    public static function terminals(): iterable
    {
        // As the gateway tells them: typesocket, versionminitel
        yield 'adapter' => [new Terminal('192.0.2.1', 'websocket', 'Cv5'), true, false];
        yield 'emulator' => [new Terminal('192.0.2.1', 'websocket', '???'), false, false];
        // A websocket whose Minitel isn't known either: not an adapter
        yield 'websocket without version' => [new Terminal('192.0.2.1', 'websocket'), false, false];
        yield 'telnet' => [new Terminal('192.0.2.1', 'other', '???'), false, true];
        yield 'phone' => [new Terminal('CALLFROM 0102030405', 'other', 'Cv5'), false, true];
        yield 'unknown' => [new Terminal(), false, false];
    }

    #[DataProvider('terminals')]
    public function testTellsHowTheUserConnects(Terminal $terminal, bool $adapter, bool $telnetOrPhone): void
    {
        self::assertSame($adapter, $terminal->isAdapter());
        self::assertSame($telnetOrPhone, $terminal->isTelnetOrPhone());
    }
}
