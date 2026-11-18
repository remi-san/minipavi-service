<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Gateway;

use MiniPaviService\Core\Signing\HmacContextSigner;
use MiniPaviService\Gateway\CallMode;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\Gateway\InputCommand;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\TestCase;

final class GatewayResponseTest extends TestCase
{
    public function testSignKeepsAllButTheContext(): void
    {
        $signer = new HmacContextSigner('secret');
        $answer = new GatewayResponse(
            new Vdt('x'),
            '{"route":"menu"}',
            'https://example.org/',
            InputCommand::text(1, row: 2, length: 4, validWith: [FunctionKey::Send]),
            CallMode::Connect,
            false,
        );

        self::assertEquals(
            new GatewayResponse(
                new Vdt('x'),
                $signer->sign('{"route":"menu"}'),
                'https://example.org/',
                InputCommand::text(1, row: 2, length: 4, validWith: [FunctionKey::Send]),
                CallMode::Connect,
                false,
            ),
            $answer->sign($signer),
        );
    }
}
