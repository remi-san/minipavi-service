<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Controller;

use MiniPaviService\Controller\ControllerState;
use MiniPaviService\Controller\Pagination\PageParameters;
use MiniPaviService\Controller\Parameters\NoParameters;
use MiniPaviService\Controller\Request;
use MiniPaviService\Gateway\ConnectionCode;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\Terminal;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function testCarriesTheUserInput(): void
    {
        $terminal = new Terminal('CALLFROM 0102030405', 'other', '???');
        $request = new Request(
            ControllerState::ReadInput,
            new PageParameters(2),
            FunctionKey::Send,
            ['Hello ', 'world'],
            $terminal,
        );

        self::assertSame(ControllerState::ReadInput, $request->state());
        self::assertEquals(new PageParameters(2), $request->parameters());
        self::assertSame(FunctionKey::Send, $request->key());
        self::assertSame(['Hello ', 'world'], $request->content());
        self::assertSame('Hello world', $request->contentAsString());
        self::assertSame($terminal, $request->terminal());
    }

    public function testFunctionKeyIsNullForConnectionCodes(): void
    {
        self::assertSame(
            FunctionKey::Guide,
            (new Request(ControllerState::Init, key: FunctionKey::Guide))->functionKey(),
        );
        self::assertNull((new Request(ControllerState::Init, key: ConnectionCode::Connect))->functionKey());
        self::assertNull((new Request(ControllerState::Init))->functionKey());
    }

    public function testConnectionCodeIsNullForFunctionKeys(): void
    {
        self::assertSame(
            ConnectionCode::Connect,
            (new Request(ControllerState::Init, key: ConnectionCode::Connect))->connectionCode(),
        );
        self::assertNull((new Request(ControllerState::Init, key: FunctionKey::Guide))->connectionCode());
    }

    public function testWithoutInputNothingWasTyped(): void
    {
        $request = new Request(ControllerState::Init);

        self::assertSame(ControllerState::Init, $request->state());
        self::assertEquals(new NoParameters(), $request->parameters());
        self::assertNull($request->key());
        self::assertSame([], $request->content());
        self::assertSame('', $request->contentAsString());
        self::assertEquals(new Terminal(), $request->terminal());
    }

    public function testTerminalTellsWhereTheUserIs(): void
    {
        $terminal = new Terminal('192.0.2.1', 'websocket', 'Cv5');

        self::assertSame('192.0.2.1', $terminal->remoteAddr());
        // A websocket with a known Minitel: an ESP32 or Minimit adapter
        self::assertTrue($terminal->isAdapter());
        self::assertFalse($terminal->isTelnetOrPhone());
        self::assertFalse((new Terminal())->isAdapter());
        // The version kept: without it, not an adapter
        self::assertFalse((new Terminal('192.0.2.1', 'websocket'))->isAdapter());
    }
}
