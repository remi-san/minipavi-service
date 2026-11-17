<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Gateway;

use MiniPaviService\Core\Signing\ForgedContextException;
use MiniPaviService\Core\Signing\HmacContextSigner;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Minitel\Terminal;
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
        ] as $key => $expected) {
            $request = new GatewayRequest((string) $key);
            self::assertSame($expected, [$request->isConnecting(), $request->isDisconnecting()], (string) $key);
        }
    }

    public function testVerifyGivesTheContextWithoutItsSignature(): void
    {
        $signer = new HmacContextSigner('secret');
        $request = new GatewayRequest(
            'ENVOI',
            ['36'],
            $signer->sign('{"route":"menu"}'),
            new Terminal('192.0.2.1'),
            'http://services/Svc/',
        );

        self::assertEquals(
            new GatewayRequest('ENVOI', ['36'], '{"route":"menu"}', new Terminal('192.0.2.1'), 'http://services/Svc/'),
            $request->verify($signer),
        );
    }

    public function testVerifyRefusesAContextItDidNotSign(): void
    {
        $this->expectException(ForgedContextException::class);

        (new GatewayRequest('ENVOI', [], '{"route":"menu"}'))->verify(new HmacContextSigner('secret'));
    }

    /**
     * Their context isn't read
     */
    public function testVerifyLeavesConnectionsAndDisconnectionsAsTheyAre(): void
    {
        foreach (['CNX', 'DIRECTCNX', 'FIN'] as $key) {
            $request = new GatewayRequest($key, [], 'not signed');
            self::assertSame($request, $request->verify(new HmacContextSigner('secret')), $key);
        }
    }
}
