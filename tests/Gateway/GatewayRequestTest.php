<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Gateway;

use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Minitel\ConnectionCode;
use MiniPaviService\Minitel\FunctionKey;
use PHPUnit\Framework\TestCase;

final class GatewayRequestTest extends TestCase
{
    public function testRecognizesConnectionsAndDisconnections(): void
    {
        foreach ([
            'CNX' => [true, false],
            'DIRECTCNX' => [true, false],
            'FIN' => [false, true],
            'ENVOI' => [false, false],
            '' => [false, false],
        ] as $fctn => $expected) {
            $request = new GatewayRequest(GatewayRequest::keyOf($fctn));
            self::assertSame($expected, [$request->isConnecting(), $request->isDisconnecting()], $fctn);
        }
    }

    public function testKeyOfWhatTheGatewaySends(): void
    {
        self::assertSame(FunctionKey::Send, GatewayRequest::keyOf('ENVOI'));
        self::assertSame(ConnectionCode::Connect, GatewayRequest::keyOf('CNX'));
        self::assertSame(ConnectionCode::BackgroundCall, GatewayRequest::keyOf('BGCALL-WM-1'));
        self::assertNull(GatewayRequest::keyOf('UNKNOWN'));
        self::assertNull(GatewayRequest::keyOf(''));
    }

    public function testFctnIsTheNameOfTheKeyAsTheGatewaySendsIt(): void
    {
        self::assertSame('ENVOI', (new GatewayRequest(FunctionKey::Send))->fctn());
        self::assertSame('CNX', (new GatewayRequest(ConnectionCode::Connect))->fctn());
        self::assertSame('', (new GatewayRequest(null))->fctn());
    }

    public function testRecognizesBackgroundCalls(): void
    {
        self::assertTrue((new GatewayRequest(ConnectionCode::BackgroundCall))->isBackgroundCall());
        self::assertFalse((new GatewayRequest(FunctionKey::Send))->isBackgroundCall());
        self::assertFalse((new GatewayRequest(ConnectionCode::Disconnect))->isBackgroundCall());
    }
}
