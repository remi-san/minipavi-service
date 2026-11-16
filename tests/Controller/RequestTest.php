<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Controller;

use MiniPaviService\Controller;
use MiniPaviService\Controller\Request;
use MiniPaviService\MiniPaviClient;
use MiniPaviService\Terminal;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function testCarriesTheUserInput(): void
    {
        $terminal = new Terminal('CALLFROM 0102030405', 'other', '???');
        $request = new Request(Controller::STATE_READ_INPUT, ['page' => 2], MiniPaviClient::KEY_SEND, ['Hello ', 'world'], $terminal);

        self::assertSame(Controller::STATE_READ_INPUT, $request->state());
        self::assertSame(2, $request->parameter('page'));
        self::assertSame(MiniPaviClient::KEY_SEND, $request->key());
        self::assertSame(['Hello ', 'world'], $request->content());
        self::assertSame('Hello world', $request->contentAsString());
        self::assertSame($terminal, $request->terminal());
    }

    public function testWithoutInputNothingWasTyped(): void
    {
        $request = new Request();

        self::assertSame(Controller::STATE_INIT, $request->state());
        self::assertSame('', $request->key());
        self::assertSame([], $request->content());
        self::assertSame('', $request->contentAsString());
        self::assertEquals(new Terminal(), $request->terminal());
    }

    public function testTerminalTellsWhereTheUserIs(): void
    {
        $terminal = new Terminal('192.0.2.1', 'websocket', 'Cv5');

        self::assertSame('192.0.2.1', $terminal->remoteAddr());
        self::assertSame('websocket', $terminal->typeSocket());
        self::assertSame('Cv5', $terminal->versionMinitel());
        self::assertNull((new Terminal())->typeSocket());
    }
}
