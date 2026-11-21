<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Minitel;

use MiniPaviService\Minitel\ConnectionCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConnectionCodeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ?ConnectionCode}>
     */
    public static function keys(): iterable
    {
        yield 'connection' => ['CNX', ConnectionCode::Connect];
        yield 'connection from another service' => ['DIRECTCNX', ConnectionCode::DirectConnect];
        yield 'disconnection' => ['FIN', ConnectionCode::Disconnect];
        yield 'background call' => ['BGCALL-WM-1', ConnectionCode::BackgroundCall];
        // Only the start of a background call's code
        yield 'start of a background call alone' => ['BGCALL-WM-', null];
        yield 'function key' => ['ENVOI', null];
        yield 'nothing' => ['', null];
    }

    #[DataProvider('keys')]
    public function testCodeOfAKeyTheGatewaySent(string $key, ?ConnectionCode $code): void
    {
        self::assertSame($code, ConnectionCode::of($key));
    }
}
