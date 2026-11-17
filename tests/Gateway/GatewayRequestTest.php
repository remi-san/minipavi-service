<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Gateway;

use MiniPaviService\Gateway\GatewayRequest;
use PHPUnit\Framework\TestCase;

final class GatewayRequestTest extends TestCase
{
    public function testRecognizesConnectionsAndDisconnections(): void
    {
        foreach (['CNX' => [true, false], 'DIRECTCNX' => [true, false], 'FIN' => [false, true], 'ENVOI' => [false, false], '' => [false, false]] as $key => $expected) {
            $request = new GatewayRequest((string) $key);
            self::assertSame($expected, [$request->isConnecting(), $request->isDisconnecting()], (string) $key);
        }
    }
}
