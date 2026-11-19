<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Client;

use MiniPavi\MiniPaviCli;
use MiniPaviService\Client\MiniPaviClient;
use MiniPaviService\Client\MiniPaviExchange;
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
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\TestCase;

/**
 * The gateway's request: MiniPaviCli::start() puts the decoded JSON in its static properties,
 * set here directly. The response: what MiniPaviCli::send() writes.
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

        $request = (new MiniPaviClient())->decoded();

        self::assertSame(FunctionKey::Send, $request->key);
        self::assertSame(['ab', 'cd'], $request->content);
        self::assertSame('{"route":"home"}', $request->context);
        self::assertSame('CALLFROM 0102030405', $request->terminal->remoteAddr());
        // A websocket with a known Minitel: its version read too
        self::assertTrue($request->terminal->isAdapter());
        MiniPaviCli::$versionMinitel = '???';
        self::assertFalse((new MiniPaviClient())->decoded()->terminal->isAdapter());
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
        // Nothing known of the connection
        self::assertFalse($request->terminal->isAdapter() || $request->terminal->isTelnetOrPhone());
        // A version that isn't text is none (not '', which a websocket would take as known)
        MiniPaviCli::$typeSocket = 'websocket';
        MiniPaviCli::$versionMinitel = ['Cv5'];
        self::assertFalse((new MiniPaviClient())->decoded()->terminal->isAdapter());
        // A connection type that isn't text is none (not 'other', telnet or phone)
        MiniPaviCli::$typeSocket = ['other'];
        self::assertFalse((new MiniPaviClient())->decoded()->terminal->isTelnetOrPhone());
    }

    public function testReadsABackgroundCallTheServiceDoesntRespondTo(): void
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

    public function testRespondsToARequestItCouldntReadAtThisService(): void
    {
        $response = $this->sent(
            new GatewayResponse(new Vdt('Erreur'), '{}'),
            (new MiniPaviClient())->unreadable('http://services/Other/index.php'),
        );

        self::assertSame('http://services/Other/index.php', $response['next']);
    }

    public function testSendsTheResponseToThisService(): void
    {
        $response = $this->sent(new GatewayResponse(new Vdt('Hello'), '{"route":"home"}'));

        self::assertSame(base64_encode('Hello'), $response['content']);
        self::assertSame('{"route":"home"}', $response['context']);
        self::assertSame('http://services/Svc/index.php', $response['next']);
        self::assertSame('no', $response['directcall']);
        self::assertSame('on', $response['echo']);
        self::assertArrayNotHasKey('COMMAND', $response);
    }

    public function testSendsATextFieldAsInputTxt(): void
    {
        $response = $this->sent(
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
            $response['COMMAND'],
        );
    }

    public function testSendsAMessageFieldAsInputMsg(): void
    {
        $response = $this->sent(
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
            $response['COMMAND'],
        );
    }

    public function testSendsTheUserToAnotherService(): void
    {
        $connect = $this->sent(
            new GatewayResponse(
                new Vdt(''),
                '{}',
                new ServiceCallCommand('https://example.org/service', CallMode::Connect),
            ),
        );
        $direct = $this->sent(
            new GatewayResponse(
                new Vdt(''),
                '{}',
                new ServiceCallCommand('https://example.org/service', CallMode::Direct),
            ),
        );

        self::assertSame(['https://example.org/service', 'yes-cnx'], [$connect['next'], $connect['directcall']]);
        self::assertSame(['yes', 'on'], [$direct['directcall'], $direct['echo']]);
        // Not a command of MiniPavi's: its next URL and directcall
        self::assertArrayNotHasKey('COMMAND', $connect);
    }

    public function testIsTheOnlyFacadeOfMiniPaviCli(): void
    {
        // Besides Vdt's Videotex helpers, which don't touch the gateway's request or response
        $src = \dirname(__DIR__, levels: 2) . '/src/';
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, flags: \FilesystemIterator::SKIP_DOTS),
        );
        $users = [];
        foreach ($files as $file) {
            // A file's info is its path as text
            $path = (string) $file;
            if (!str_contains((string) file_get_contents($path), 'MiniPavi\\MiniPaviCli')) {
                continue;
            }
            $users[] = substr($path, \strlen($src));
        }
        sort($users);

        self::assertSame(['Client/MiniPaviClient.php', 'Vdt/Vdt.php'], $users);
    }

    /**
     * What the client writes for the response, decoded
     *
     * @return array<mixed>
     */
    private function sent(GatewayResponse $response, ?ClientExchange $exchange = null): array
    {
        $exchange ??= new MiniPaviExchange(
            new MiniPaviClient(),
            new GatewayRequest(FunctionKey::Send),
            self::SERVICE_URL,
        );
        ob_start();
        try {
            $httpResponse = $exchange->respond($response);
        } finally {
            $output = (string) ob_get_clean();
        }
        // What MiniPaviCli writes is the body of the response, not output
        self::assertSame('', $output);
        self::assertSame(200, $httpResponse->getStatusCode());
        $body = (string) $httpResponse->getBody();
        $sent = json_decode($body, associative: true);

        return \is_array($sent) ? $sent : self::fail("Not a response: {$body}");
    }
}
