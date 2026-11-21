<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Client;

use MiniPaviService\Client\MiniPaviClient;
use MiniPaviService\Gateway\CallMode;
use MiniPaviService\Gateway\ClientExchange;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\Gateway\InputCommand;
use MiniPaviService\Gateway\ServiceCallCommand;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\InputField;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\Span;
use MiniPaviService\Tests\Support\HttpRequests;
use MiniPaviService\Tests\Support\MemoryMiniPaviGateway;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\TestCase;

/**
 * Through a gateway of the tests: the request it reads, and what it is sent for each command
 * (MiniPaviCliGatewayTest checks what MiniPaviCli makes of them)
 */
final class MiniPaviClientTest extends TestCase
{
    // The service's URL, sent to the gateway as the next one to call
    private const SERVICE_URL = 'http://services/Svc/index.php';

    public function testGivesTheRequestTheGatewayReads(): void
    {
        $request = new GatewayRequest(FunctionKey::Send, ['ab'], '{"route":"home"}');

        $exchange = (new MiniPaviClient(new MemoryMiniPaviGateway($request)))->exchange(
            HttpRequests::to('MiniPAVI'),
            self::SERVICE_URL,
        );

        self::assertSame($request, $exchange->request());
    }

    public function testFailsWhenTheGatewayCantReadTheRequest(): void
    {
        $this->expectExceptionObject($failure = new \Exception('Not JSON'));

        (new MiniPaviClient(new MemoryMiniPaviGateway($failure)))->exchange(
            HttpRequests::to('MiniPAVI'),
            self::SERVICE_URL,
        );
    }

    public function testARequestItCouldntReadIsEmpty(): void
    {
        $gateway = new MemoryMiniPaviGateway();

        $request = (new MiniPaviClient($gateway))
            ->unreadable(self::SERVICE_URL)
            ->request();

        self::assertNull($request->key);
        self::assertSame([], $request->content);
        self::assertSame('', $request->context);
        // Not read again
        self::assertSame(0, $gateway->reads);
    }

    public function testRespondsToARequestItCouldntReadAtThisService(): void
    {
        $gateway = new MemoryMiniPaviGateway();
        $vdt = new Vdt('Erreur');

        (new MiniPaviClient($gateway))
            ->unreadable('http://services/Other/index.php')
            ->respond(new GatewayResponse($vdt, '{}'));

        self::assertSame([[$vdt, 'http://services/Other/index.php', '{}', null, null]], $gateway->sent);
    }

    public function testRespondsWithWhatTheGatewayIsSent(): void
    {
        $gateway = new MemoryMiniPaviGateway(new GatewayRequest(FunctionKey::Send));
        $vdt = new Vdt('Hello');

        $httpResponse = $this->exchange($gateway)->respond(new GatewayResponse($vdt, '{"route":"home"}'));

        self::assertSame(200, $httpResponse->getStatusCode());
        self::assertSame('response 1', (string) $httpResponse->getBody());
        // Waits for a key, then calls this service
        self::assertSame([[$vdt, self::SERVICE_URL, '{"route":"home"}', null, null]], $gateway->sent);
    }

    public function testSendsTheFieldToRead(): void
    {
        $gateway = new MemoryMiniPaviGateway(new GatewayRequest(FunctionKey::Send));
        $vdt = new Vdt('Code ?');
        $input = new InputCommand(new InputField(new Span(new Position(20, 5), 10)), ['abc']);

        $this->exchange($gateway)->respond(new GatewayResponse($vdt, '{}', command: $input));

        self::assertSame([[$vdt, self::SERVICE_URL, '{}', $input, null]], $gateway->sent);
    }

    public function testSendsTheUserToAnotherService(): void
    {
        $gateway = new MemoryMiniPaviGateway(new GatewayRequest(FunctionKey::Send));
        $vdt = new Vdt('');

        $this->exchange($gateway)->respond(
            new GatewayResponse($vdt, '{}', new ServiceCallCommand('https://example.org/service', CallMode::Connect)),
        );

        self::assertSame([[$vdt, 'https://example.org/service', '{}', null, CallMode::Connect]], $gateway->sent);
    }

    private function exchange(MemoryMiniPaviGateway $gateway): ClientExchange
    {
        return (new MiniPaviClient($gateway))->exchange(HttpRequests::to('MiniPAVI'), self::SERVICE_URL);
    }
}
