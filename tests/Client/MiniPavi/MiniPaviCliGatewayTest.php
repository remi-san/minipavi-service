<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Client\MiniPavi;

use MiniPaviService\Client\MiniPavi\MiniPaviCliGateway;
use MiniPaviService\Gateway\Command\CallMode;
use MiniPaviService\Gateway\Command\InputCommand;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Minitel\ConnectionCode;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\InputField;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\Span;
use MiniPaviService\Tests\Support\PhpInput;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Against the real MiniPaviCli: the gateway's request is the JSON MiniPavi posts (the body
 * of the HTTP request, php://input), the response what MiniPaviCli::send() writes.
 */
final class MiniPaviCliGatewayTest extends TestCase
{
    protected function setUp(): void
    {
        // MiniPaviCli::start() sends other user agents to the emulator
        $_SERVER['HTTP_USER_AGENT'] = 'MiniPAVI';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_USER_AGENT']);
    }

    public function testReadsTheRequest(): void
    {
        $request = $this->read([
            'fctn' => 'ENVOI',
            'content' => ['ab', 'cd'],
            'context' => '{"route":"home"}',
            'remoteAddr' => 'CALLFROM 0102030405',
            'typesocket' => 'websocket',
            'versionminitel' => 'Cv5',
        ]);

        self::assertSame(FunctionKey::Send, $request->key);
        self::assertSame(['ab', 'cd'], $request->content);
        self::assertSame('{"route":"home"}', $request->context);
        self::assertSame('CALLFROM 0102030405', $request->terminal->remoteAddr);
        // A websocket with a known Minitel: its version read too
        self::assertTrue($request->terminal->isAdapter());
        self::assertFalse($this->read(['typesocket' => 'websocket', 'versionminitel' => '???'])->terminal->isAdapter());
    }

    public function testValuesThatAreNotTextBecomeEmpty(): void
    {
        $request = $this->read([
            'fctn' => null,
            'context' => ['not', 'text'],
            'remoteAddr' => null,
            'typesocket' => new \stdClass(),
            'versionminitel' => null,
        ]);

        self::assertSame([null, ''], [$request->key, $request->context]);
        self::assertSame('', $request->terminal->remoteAddr);
        // Nothing known of the connection
        self::assertFalse($request->terminal->isAdapter() || $request->terminal->isTelnetOrPhone());
        // A version that isn't text is none (not '', which a websocket would take as known)
        self::assertFalse(
            $this->read(['typesocket' => 'websocket', 'versionminitel' => ['Cv5']])->terminal->isAdapter(),
        );
        // A connection type that isn't text is none (not 'other', telnet or phone)
        self::assertFalse($this->read(['typesocket' => ['other']])->terminal->isTelnetOrPhone());
    }

    public function testAVersionThatIsANumberIsReadAsItsText(): void
    {
        // A known Minitel, as '5' would be
        self::assertTrue($this->read(['typesocket' => 'websocket', 'versionminitel' => 5])->terminal->isAdapter());
    }

    public function testContentIsAListOfTexts(): void
    {
        self::assertSame(['one line'], $this->read(['content' => 'one line'])->content);
        self::assertSame(['a', '12', '', ''], $this->read(['content' => ['a', 12, null, ['x']]])->content);
        // An object is a value that isn't text
        self::assertSame([''], $this->read(['content' => ['line' => 'a']])->content);
        self::assertSame([], $this->read(['content' => null])->content);
        self::assertSame([], $this->read([])->content);
    }

    /**
     * In a separate process: MiniPaviCli::start() would exit on it, unless told not to
     */
    #[RunInSeparateProcess]
    public function testReadsABackgroundCallTheServiceDoesntRespondTo(): void
    {
        self::assertSame(ConnectionCode::BackgroundCall, $this->read(['fctn' => 'BGCALL-WM-1'])->key);
    }

    public function testFailsOnARequestThatIsNotJson(): void
    {
        $this->expectException(\JsonException::class);
        $this->expectExceptionMessage('Syntax error');

        PhpInput::read('not JSON', static fn() => (new MiniPaviCliGateway())->read());
    }

    public function testSendsTheResponse(): void
    {
        $response = $this->sent(new Vdt('Hello'), next: 'http://services/Svc/index.php', context: '{"route":"home"}');

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
            new Vdt('Code ?'),
            next: 'http://services/Svc/index.php',
            context: '{}',
            input: new InputCommand(
                new InputField(new Span(new Position(20, 5), 10), validWith: [FunctionKey::Send, FunctionKey::Next]),
                ['abc'],
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
            new Vdt('Message ?'),
            next: 'http://services/Svc/index.php',
            context: '{}',
            input: new InputCommand(
                new InputField(
                    new Span(new Position(13, 1), 40),
                    lines: 2,
                    validWith: [FunctionKey::Send],
                    cursor: false,
                ),
                ['l1', 'l2'],
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

    public function testCallsTheNextUrlAtOnce(): void
    {
        $connect = $this->sent(
            new Vdt(''),
            next: 'https://example.org/service',
            context: '{}',
            directCall: CallMode::Connect,
        );
        $direct = $this->sent(
            new Vdt(''),
            next: 'https://example.org/service',
            context: '{}',
            directCall: CallMode::Direct,
        );

        self::assertSame(['https://example.org/service', 'yes-cnx'], [$connect['next'], $connect['directcall']]);
        self::assertSame(['yes', 'on'], [$direct['directcall'], $direct['echo']]);
        self::assertArrayNotHasKey('COMMAND', $connect);
    }

    public function testIsTheOnlyFacadeOfMiniPaviCli(): void
    {
        $src = \dirname(__DIR__, levels: 3) . '/src/';
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, flags: \FilesystemIterator::SKIP_DOTS),
        );
        $users = [];
        foreach ($files as $file) {
            // A file's info is its path as text
            $path = (string) $file;
            // The library's class, not the namespace Client\MiniPavi of the framework's classes
            if (!preg_match('/(?<!\w)(?<!\w\\\\)MiniPavi\\\\MiniPaviCli(?!\w)/', (string) file_get_contents($path))) {
                continue;
            }
            $users[] = substr($path, \strlen($src));
        }
        sort($users);

        self::assertSame(['Client/MiniPavi/MiniPaviCliGateway.php'], $users);
    }

    /**
     * The request read from what MiniPavi posts: these values of its PAVI object
     *
     * @param array<string, mixed> $pavi
     */
    private function read(array $pavi): GatewayRequest
    {
        return PhpInput::read(
            json_encode(['PAVI' => (object) $pavi], \JSON_THROW_ON_ERROR),
            static fn() => (new MiniPaviCliGateway())->read(),
        );
    }

    /**
     * The response MiniPavi is sent, decoded
     *
     * @return array<mixed>
     */
    private function sent(
        Vdt $vdt,
        string $next,
        string $context,
        ?InputCommand $input = null,
        ?CallMode $directCall = null,
    ): array {
        ob_start();
        try {
            $body = (new MiniPaviCliGateway())->send($vdt, $next, $context, $input, $directCall);
        } finally {
            $output = (string) ob_get_clean();
        }
        // What MiniPaviCli writes is the body of the response, not output
        self::assertSame('', $output);
        $sent = json_decode($body, associative: true);

        return \is_array($sent) ? $sent : self::fail("Not a response: {$body}");
    }
}
