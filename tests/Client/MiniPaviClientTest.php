<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Client;

use MiniPavi\MiniPaviCli;
use MiniPaviService\Client\MiniPaviClient;
use MiniPaviService\Gateway\CallMode;
use MiniPaviService\Gateway\GatewayAnswer;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Http\RequestUrl;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\TestCase;

/**
 * The gateway's request: MiniPaviCli::start() puts the decoded JSON in its static properties,
 * set here directly. The answer: what MiniPaviCli::send() writes.
 */
final class MiniPaviClientTest extends TestCase
{
    /** @var array<mixed> */
    private array $server;

    protected function setUp(): void
    {
        // The service's URL, sent to the gateway as the next one to call
        $this->server = $_SERVER;
        $_SERVER['HTTP_HOST'] = 'services';
        $_SERVER['PHP_SELF'] = '/Svc/index.php';
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
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
            new GatewayRequest('ENVOI', ['ab', 'cd'], '{"route":"home"}', new Terminal('CALLFROM 0102030405', 'websocket', 'Cv5')),
            (new MiniPaviClient(new RequestUrl()))->request(),
        );
    }

    public function testValuesThatAreNotTextBecomeEmpty(): void
    {
        MiniPaviCli::$fctn = null;
        MiniPaviCli::$context = ['not', 'text'];
        MiniPaviCli::$remoteAddr = null;
        MiniPaviCli::$typeSocket = new \stdClass();
        MiniPaviCli::$versionMinitel = null;

        self::assertEquals(new GatewayRequest('', [], '', new Terminal('', null, null)), (new MiniPaviClient(new RequestUrl()))->request());
    }

    public function testContentIsAListOfTexts(): void
    {
        $client = new MiniPaviClient(new RequestUrl());

        MiniPaviCli::$content = 'one line';
        self::assertSame(['one line'], $client->request()->content);

        MiniPaviCli::$content = [2 => 'a', 5 => 12, 7 => null, 9 => ['x']];
        self::assertSame(['a', '12', '', ''], $client->request()->content);

        MiniPaviCli::$content = null;
        self::assertSame([], $client->request()->content);
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
            (new MiniPaviClient(new RequestUrl()))->send($answer);
        } finally {
            $output = (string) ob_get_clean();
        }
        $sent = json_decode($output, true);

        return \is_array($sent) ? $sent : self::fail("Not an answer: $output");
    }
}
