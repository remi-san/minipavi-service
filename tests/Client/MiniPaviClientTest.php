<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Client;

use MiniPavi\MiniPaviCli;
use MiniPaviService\Client\MiniPaviClient;
use MiniPaviService\Gateway\CallMode;
use MiniPaviService\Gateway\GatewayAnswer;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Http\EmptyHttpResponse;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\TestCase;

/**
 * The gateway's request: MiniPaviCli::start() puts the decoded JSON in its static properties,
 * set here directly. The answer: what MiniPaviCli::send() writes.
 */
final class MiniPaviClientTest extends TestCase
{
    // The service's URL, sent to the gateway as the next one to call
    private const SERVICE_URL = 'http://services/Svc/index.php';

    protected function tearDown(): void
    {
        MiniPaviCli::$fctn = '';
        MiniPaviCli::$content = [];
        MiniPaviCli::$context = '';
        MiniPaviCli::$remoteAddr = '';
        MiniPaviCli::$typeSocket = null;
        MiniPaviCli::$versionMinitel = null;
    }

    public function testReadsTheRequest(): void
    {
        MiniPaviCli::$fctn = 'ENVOI';
        MiniPaviCli::$content = ['ab', 'cd'];
        MiniPaviCli::$context = '{"route":"home"}';
        MiniPaviCli::$remoteAddr = 'CALLFROM 0102030405';
        MiniPaviCli::$typeSocket = 'websocket';
        MiniPaviCli::$versionMinitel = 'Cv5';

        self::assertEquals(
            new GatewayRequest('ENVOI', ['ab', 'cd'], '{"route":"home"}', new Terminal('CALLFROM 0102030405', 'websocket', 'Cv5'), self::SERVICE_URL),
            (new MiniPaviClient())->decoded(self::SERVICE_URL),
        );
    }

    public function testValuesThatAreNotTextBecomeEmpty(): void
    {
        MiniPaviCli::$fctn = null;
        MiniPaviCli::$context = ['not', 'text'];
        MiniPaviCli::$remoteAddr = null;
        MiniPaviCli::$typeSocket = new \stdClass();
        MiniPaviCli::$versionMinitel = null;

        self::assertEquals(new GatewayRequest('', [], '', new Terminal('', null, null)), (new MiniPaviClient())->decoded());
    }

    public function testContentIsAListOfTexts(): void
    {
        $client = new MiniPaviClient();

        MiniPaviCli::$content = 'one line';
        self::assertSame(['one line'], $client->decoded()->content);

        MiniPaviCli::$content = [2 => 'a', 5 => 12, 7 => null, 9 => ['x']];
        self::assertSame(['a', '12', '', ''], $client->decoded()->content);

        MiniPaviCli::$content = null;
        self::assertSame([], $client->decoded()->content);
    }

    public function testSendsTheAnswerToThisService(): void
    {
        $answer = $this->sent(new GatewayAnswer(new Vdt('Hello'), '{"route":"home"}'));

        self::assertSame(base64_encode('Hello'), $answer['content']);
        self::assertSame('{"route":"home"}', $answer['context']);
        self::assertSame('http://services/Svc/index.php', $answer['next']);
        self::assertSame('no', $answer['directcall']);
        self::assertSame('on', $answer['echo']);
        self::assertArrayNotHasKey('COMMAND', $answer);
    }

    public function testSendsTheCommandWithTheAnswer(): void
    {
        $answer = $this->sent(new GatewayAnswer(new Vdt('Code ?'), '{}', command: ['COMMAND' => ['name' => 'InputTxt', 'param' => ['x' => 1]]]));

        self::assertSame(['name' => 'InputTxt', 'param' => ['x' => 1]], $answer['COMMAND']);
    }

    public function testSendsTheUserToAnotherService(): void
    {
        $connect = $this->sent(new GatewayAnswer(new Vdt(''), '{}', 'https://example.org/service', directCall: CallMode::Connect));
        $direct = $this->sent(new GatewayAnswer(new Vdt(''), '{}', 'https://example.org/service', directCall: CallMode::Direct, echo: false));

        self::assertSame(['https://example.org/service', 'yes-cnx'], [$connect['next'], $connect['directcall']]);
        self::assertSame(['yes', 'off'], [$direct['directcall'], $direct['echo']]);
    }

    /**
     * What the client writes for the answer, decoded
     *
     * @return array<mixed>
     */
    private function sent(GatewayAnswer $answer): array
    {
        ob_start();
        try {
            $response = (new MiniPaviClient())->response(new GatewayRequest('ENVOI', serviceUrl: self::SERVICE_URL), $answer);
        } finally {
            $output = (string) ob_get_clean();
        }
        // Written by MiniPaviCli: the HTTP response isn't sent
        self::assertInstanceOf(EmptyHttpResponse::class, $response);
        $sent = json_decode($output, true);

        return \is_array($sent) ? $sent : self::fail("Not an answer: $output");
    }
}
