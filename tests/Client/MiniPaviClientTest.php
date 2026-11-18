<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Client;

use MiniPavi\MiniPaviCli;
use MiniPaviService\Client\MiniPaviClient;
use MiniPaviService\Gateway\CallMode;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\Gateway\InputCommand;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\InputField;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\Span;
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

        $request = (new MiniPaviClient())->decoded(self::SERVICE_URL);

        self::assertSame(FunctionKey::Send, $request->key);
        self::assertSame(['ab', 'cd'], $request->content);
        self::assertSame('{"route":"home"}', $request->context);
        self::assertSame('CALLFROM 0102030405', $request->terminal->remoteAddr());
        self::assertSame('websocket', $request->terminal->typeSocket());
        self::assertSame('Cv5', $request->terminal->versionMinitel());
        // The next URL of answers without one
        self::assertSame(self::SERVICE_URL, $request->serviceUrl);
    }

    public function testValuesThatAreNotTextBecomeEmpty(): void
    {
        MiniPaviCli::$fctn = null;
        MiniPaviCli::$context = ['not', 'text'];
        MiniPaviCli::$remoteAddr = null;
        MiniPaviCli::$typeSocket = new \stdClass();
        MiniPaviCli::$versionMinitel = null;

        $request = (new MiniPaviClient())->decoded();

        self::assertSame([null, ''], [$request->key, $request->context]);
        self::assertSame('', $request->terminal->remoteAddr());
        self::assertNull($request->terminal->typeSocket());
        self::assertNull($request->terminal->versionMinitel());
    }

    public function testReadsABackgroundCallForTheServiceToAnswerNothing(): void
    {
        MiniPaviCli::$fctn = 'BGCALL-WM-1';

        self::assertTrue((new MiniPaviClient())->decoded()->isBackgroundCall());
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
        $answer = $this->sent(new GatewayResponse(new Vdt('Hello'), '{"route":"home"}'));

        self::assertSame(base64_encode('Hello'), $answer['content']);
        self::assertSame('{"route":"home"}', $answer['context']);
        self::assertSame('http://services/Svc/index.php', $answer['next']);
        self::assertSame('no', $answer['directcall']);
        self::assertSame('on', $answer['echo']);
        self::assertArrayNotHasKey('COMMAND', $answer);
    }

    public function testSendsATextFieldAsInputTxt(): void
    {
        $answer = $this->sent(
            new GatewayResponse(
                new Vdt('Code ?'),
                '{}',
                command: new InputCommand(
                    new InputField(
                        new Span(new Position(20, 5), 10),
                        validWith: [FunctionKey::Send, FunctionKey::Next],
                    ),
                    ['abc'],
                ),
            ),
        );

        self::assertSame(
            [
                'name' => 'InputTxt',
                'param' => [
                    'x' => 5,
                    'y' => 20,
                    'l' => 10,
                    'char' => '',
                    'spacechar' => '.',
                    'prefill' => 'abc',
                    'cursor' => 'on',
                    'validwith' => FunctionKey::masks(FunctionKey::Send, FunctionKey::Next),
                ],
            ],
            $answer['COMMAND'],
        );
    }

    public function testSendsAMessageFieldAsInputMsg(): void
    {
        $answer = $this->sent(
            new GatewayResponse(
                new Vdt('Message ?'),
                '{}',
                command: new InputCommand(
                    new InputField(
                        new Span(new Position(13, 1), 40),
                        lines: 2,
                        validWith: [FunctionKey::Send],
                        cursor: false,
                    ),
                    ['l1', 'l2'],
                ),
            ),
        );

        self::assertSame(
            [
                'name' => 'InputMsg',
                'param' => [
                    'x' => 1,
                    'y' => 13,
                    'w' => 40,
                    'h' => 2,
                    'spacechar' => '.',
                    'prefill' => ['l1', 'l2'],
                    'cursor' => 'off',
                    'validwith' => FunctionKey::Send->mask(),
                ],
            ],
            $answer['COMMAND'],
        );
    }

    public function testSendsTheUserToAnotherService(): void
    {
        $connect = $this->sent(
            new GatewayResponse(new Vdt(''), '{}', 'https://example.org/service', directCall: CallMode::Connect),
        );
        $direct = $this->sent(
            new GatewayResponse(new Vdt(''), '{}', 'https://example.org/service', directCall: CallMode::Direct),
        );

        self::assertSame(['https://example.org/service', 'yes-cnx'], [$connect['next'], $connect['directcall']]);
        self::assertSame(['yes', 'on'], [$direct['directcall'], $direct['echo']]);
    }

    /**
     * What the client writes for the answer, decoded
     *
     * @return array<mixed>
     */
    private function sent(GatewayResponse $answer): array
    {
        ob_start();
        try {
            $response = (new MiniPaviClient())->response(
                new GatewayRequest(FunctionKey::Send, serviceUrl: self::SERVICE_URL),
                $answer,
            );
        } finally {
            $output = (string) ob_get_clean();
        }
        // What MiniPaviCli writes is the body of the response, not output
        self::assertSame('', $output);
        self::assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $sent = json_decode($body, associative: true);

        return \is_array($sent) ? $sent : self::fail("Not an answer: {$body}");
    }
}
