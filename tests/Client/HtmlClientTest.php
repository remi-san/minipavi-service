<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Client;

use MiniPaviService\Client\HtmlClient;
use MiniPaviService\Client\HtmlForm;
use MiniPaviService\Controller\Parameters\NoParameters;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;
use MiniPaviService\Core\ContextSerializer;
use MiniPaviService\Core\RouterBuilder;
use MiniPaviService\Core\Service;
use MiniPaviService\Core\Signing\HmacContextSigner;
use MiniPaviService\Gateway\CallMode;
use MiniPaviService\Gateway\ConnectionCode;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\Gateway\InputCommand;
use MiniPaviService\Gateway\ServiceCallCommand;
use MiniPaviService\Http\ServiceRunner;
use MiniPaviService\Message\FrameworkMessage;
use MiniPaviService\Message\Messages;
use MiniPaviService\Message\MessagesXmlParser;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\InputField;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\Span;
use MiniPaviService\Tests\Support\CallbackController;
use MiniPaviService\Tests\Support\HttpRequests;
use MiniPaviService\Tests\Support\MemoryLogger;
use MiniPaviService\Vdt\Screen\HtmlRenderer;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HtmlClientTest extends TestCase
{
    private const SERVICE_URL = 'http://services/Svc/index.php';

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function connections(): iterable
    {
        yield 'web' => [['web' => 'true']];
        // No other route than the default one: anything else is in the context
        yield 'a route' => [['web' => 'true', 'route' => 'games']];
    }

    /**
     * @param array<string, string> $query
     */
    #[DataProvider('connections')]
    public function testFirstRequestIsAConnection(array $query): void
    {
        self::assertEquals(
            new GatewayRequest(ConnectionCode::Connect),
            self::client()->exchange(HttpRequests::to('Mozilla/5.0', $query), self::SERVICE_URL)->request(),
        );
    }

    public function testKeyPressedWithWhatWasTyped(): void
    {
        // The page the key is pressed on: its hidden values, posted back as they are
        $screen = Vdt::position(1, row: 1)->append(Vdt::text('Menu'));
        $page = self::page(
            new GatewayResponse(
                $screen,
                '{"route":"menu"}',
                command: new InputCommand(
                    new InputField(
                        new Span(new Position(2, 1), 4),
                        validWith: [FunctionKey::Send, FunctionKey::Next],
                        cursor: false,
                        spaceChar: ' ',
                    ),
                    ['ab'],
                ),
            ),
        );
        $form = ['key' => 'ENVOI', 'content' => ['36', '15']] + self::hiddenValues($page);

        $exchange = self::client()
            ->exchange(
                HttpRequests::posting('Mozilla/5.0', ['web' => 'true', 'route' => 'games'], $form),
                self::SERVICE_URL,
            );

        self::assertEquals(
            new GatewayRequest(FunctionKey::Send, ['36', '15'], '{"route":"menu"}'),
            $exchange->request(),
        );
        // With the page posted back: a response leaving the service shows it again, its screen
        // and its input field
        $html = (string) $exchange->respond(new GatewayResponse(new Vdt(), '{}', self::leaving()))->getBody();
        self::assertStringContainsString('Menu', self::shownText($html));
        self::assertStringContainsString(
            'value="ab" style="' . (new HtmlRenderer(HtmlRenderer::MINITEL_FONT))->place(1, row: 2, length: 4) . '"',
            $html,
        );
        // Its keys: Envoi and Suite only
        self::assertSame(6, substr_count($html, needle: ' disabled>'));
        // The whole command, cursor and space character included, posted back as it came
        self::assertSame(self::hiddenValues($page)['command'], self::hiddenValues($html)['command']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function commandsThatCantBeRead(): iterable
    {
        yield 'not JSON' => ['not a command'];
        yield 'without its field' => ['{"validWith":["ENVOI"]}'];
        yield 'off the screen' => ['{"row":30,"col":1,"length":4,"lines":1}'];
    }

    #[DataProvider('commandsThatCantBeRead')]
    public function testCommandPostedBackThatCantBeReadIsNone(string $command): void
    {
        $form = ['key' => 'ENVOI', 'command' => $command];

        $exchange = self::client()
            ->exchange(HttpRequests::posting('Mozilla/5.0', ['web' => 'true'], $form), self::SERVICE_URL);

        // Shown again when leaving the service is blocked: no field
        $html = (string) $exchange->respond(new GatewayResponse(new Vdt(), '{}', self::leaving()))->getBody();
        self::assertStringNotContainsString('class="field"', $html);
    }

    public function testARequestItCouldntReadGetsABlankPageOfThisService(): void
    {
        $html = (string) self::client()
            ->unreadable('http://services/Other/index.php')
            ->respond(new GatewayResponse(Vdt::line0('Erreur'), '{}'))
            ->getBody();

        self::assertStringContainsString('<title>http://services/Other/index.php</title>', $html);
        self::assertStringContainsString('Erreur', self::shownText($html));
        // No field: no page was posted
        self::assertStringNotContainsString('class="field"', $html);
    }

    public function testConnectionIsRespondedToAtTheUrlItWasSentTo(): void
    {
        $html = (string) self::client()
            ->exchange(HttpRequests::to('Mozilla/5.0', [
                'web' => 'true',
            ]), serviceUrl: 'http://services/Other/index.php')
            ->respond(new GatewayResponse(new Vdt(), '{}'))
            ->getBody();

        self::assertStringContainsString('<title>http://services/Other/index.php</title>', $html);
    }

    public function testUnknownKeyIsAConnection(): void
    {
        self::assertSame(
            ConnectionCode::Connect,
            self::client()
                ->exchange(HttpRequests::posting('Mozilla/5.0', ['web' => 'true'], ['key' => 'CNX']), self::SERVICE_URL)
                ->request()
                ->key,
        );
    }

    public function testPageKeepsTheContextAndTheScreen(): void
    {
        $vdt = Vdt::clearScreen()->append(Vdt::position(1, row: 1), Vdt::text('Bonjour <3'));
        $html = self::page(new GatewayResponse($vdt, '{"route":"home"}'));

        self::assertStringStartsWith('<!doctype html>', $html);
        self::assertStringContainsString('<form method="post" class="page">', $html);
        self::assertStringContainsString('<title>' . self::SERVICE_URL . '</title>', $html);
        self::assertStringContainsString('Bonjour <3', self::shownText($html));
        self::assertStringContainsString(
            '<input type="hidden" name="context" value="{&quot;route&quot;:&quot;home&quot;}">',
            $html,
        );
        // The screen from its clear screen (FF)
        self::assertStringContainsString(
            '<input type="hidden" name="screen" value="' . base64_encode($vdt->fromLastClear()->toString()) . '">',
            $html,
        );
    }

    public function testPageIsSelfContainedWithTheMinitelFont(): void
    {
        $html = self::page(new GatewayResponse(new Vdt(), ''));

        self::assertSame(1, substr_count($html, needle: '<div class="minitel">'));
        self::assertStringContainsString(
            '@font-face { font-family: Minitel; src: url(data:font/ttf;base64,'
            . base64_encode((string) file_get_contents(HtmlRenderer::MINITEL_FONT))
            . ') format("truetype"); }',
            $html,
        );
        self::assertStringNotContainsString('<script', $html);
    }

    public function testTitleOfThePageIsEscaped(): void
    {
        $messages = (new MessagesXmlParser())->defaults();
        $form = new HtmlForm(new HtmlRenderer(HtmlRenderer::MINITEL_FONT), $messages);

        self::assertStringContainsString('<title>Svc &lt;a &amp; &quot;b&quot;&gt;</title>', $form->render(
            new Vdt(),
            context: '',
            command: null,
            title: 'Svc <a & "b">',
        ));
    }

    public function testResponseIsDrawnOverTheScreenSoFar(): void
    {
        $before = Vdt::clearScreen()->append(Vdt::position(1, row: 3), Vdt::text('Page 2/2'));

        $html = self::page(new GatewayResponse(Vdt::line0('Dernière page'), '{}'), [
            'key' => 'SUITE',
            'screen' => base64_encode($before->toString()),
        ]);

        self::assertStringContainsString('Page 2/2', self::shownText($html));
        self::assertStringContainsString('Dernière page', self::shownText($html));
    }

    public function testScreenIsKeptFromItsLastClear(): void
    {
        $before = Vdt::clearScreen()->append(Vdt::position(1, row: 3), Vdt::text('Avant'));
        $response = Vdt::clearScreen()->append(Vdt::position(1, row: 3), Vdt::text('Après'));

        $html = self::page(new GatewayResponse($response, '{}'), [
            'key' => 'SUITE',
            'screen' => base64_encode($before->toString()),
        ]);

        self::assertStringContainsString(
            '<input type="hidden" name="screen" value="' . base64_encode($response->fromLastClear()->toString()) . '">',
            $html,
        );
        self::assertStringNotContainsString('Avant', self::shownText($html));
    }

    public function testTextFieldOverTheScreen(): void
    {
        $html = self::page(
            new GatewayResponse(
                new Vdt(),
                '{}',
                command: new InputCommand(
                    new InputField(new Span(new Position(24, 16), 2), validWith: [FunctionKey::Send]),
                    ['ab'],
                ),
            ),
        );

        self::assertSame(1, substr_count($html, needle: 'class="field"'));
        self::assertStringContainsString(
            'name="content[]" maxlength="2" value="ab" style="'
            . (new HtmlRenderer(HtmlRenderer::MINITEL_FONT))->place(16, row: 24, length: 2)
            . '" autofocus',
            $html,
        );
    }

    public function testMessageFieldHasAnInputPerLine(): void
    {
        $html = self::page(
            new GatewayResponse(
                new Vdt(),
                '{}',
                command: new InputCommand(
                    new InputField(new Span(new Position(13, 1), 40), lines: 2, validWith: [FunctionKey::Send]),
                    ['l1', 'l2'],
                ),
            ),
        );

        self::assertStringContainsString(
            'value="l1" style="' . (new HtmlRenderer(HtmlRenderer::MINITEL_FONT))->place(1, row: 13, length: 40) . '"',
            $html,
        );
        self::assertStringContainsString(
            'value="l2" style="' . (new HtmlRenderer(HtmlRenderer::MINITEL_FONT))->place(1, row: 14, length: 40) . '"',
            $html,
        );
    }

    public function testKeysTheInputDoesntAcceptAreDisabled(): void
    {
        $html = self::page(
            new GatewayResponse(
                new Vdt(),
                '{}',
                command: new InputCommand(
                    new InputField(new Span(new Position(1, 1), 1), validWith: [FunctionKey::Send, FunctionKey::Next]),
                ),
            ),
        );

        self::assertSame(8, substr_count($html, needle: 'name="key"'));
        self::assertSame(6, substr_count($html, needle: ' disabled>'));
        self::assertMatchesRegularExpression('#value="SUITE" style="order: \d+">Suite</button>#', $html);
        // Envoi first: the key Enter presses in a field
        self::assertMatchesRegularExpression(
            '#<div class="keys"><button type="submit" name="key" value="ENVOI"#',
            $html,
        );
    }

    public function testWithoutInputEveryKeyIsEnabled(): void
    {
        $html = self::page(new GatewayResponse(new Vdt(), '{}'));

        self::assertSame(8, substr_count($html, needle: 'name="key"'));
        self::assertStringNotContainsString(' disabled>', $html);
        self::assertStringNotContainsString('class="field"', $html);
    }

    public function testKeyLabelsAreMessages(): void
    {
        $html = self::page(new GatewayResponse(new Vdt(), '{}'), messages: (new MessagesXmlParser())
            ->defaults()
            ->with([
                'key.send' => 'Valider',
            ]));

        self::assertStringContainsString('value="ENVOI" style="order: 7">Valider</button>', $html);
    }

    public function testLeavingTheServiceIsBlocked(): void
    {
        // The page the key is pressed on: its hidden values, posted back as they are
        $before = Vdt::clearScreen()->append(Vdt::position(1, row: 3), Vdt::text('Annuaire'));
        $page = self::page(
            new GatewayResponse(
                $before,
                '{"route":"directory"}',
                command: new InputCommand(
                    new InputField(new Span(new Position(23, 7), 23), validWith: [FunctionKey::Send]),
                ),
            ),
        );
        $form = ['key' => 'ENVOI'] + self::hiddenValues($page);

        $html = self::page(
            new GatewayResponse(Vdt::line0('Cnx en cours'), '{"route":"elsewhere"}', self::leaving()),
            $form,
        );

        $shown = self::shownText($html);
        self::assertStringContainsString('Annuaire', $shown);
        self::assertStringContainsString(
            (new MessagesXmlParser())->defaults()->get(FrameworkMessage::WebLeave),
            $shown,
        );
        self::assertStringNotContainsString('Cnx en cours', $shown);
        // Where the user was: their context and their input
        self::assertStringContainsString('name="context" value="{&quot;route&quot;:&quot;directory&quot;}"', $html);
        self::assertStringContainsString(
            'style="' . (new HtmlRenderer(HtmlRenderer::MINITEL_FONT))->place(7, row: 23, length: 23) . '"',
            $html,
        );
        self::assertSame(7, substr_count($html, needle: ' disabled>'));
    }

    public function testCallingTheServiceIsntLeaving(): void
    {
        $html = self::page(
            new GatewayResponse(
                Vdt::position(1, row: 1)->append(Vdt::text('Ici')),
                '{}',
                new ServiceCallCommand(self::SERVICE_URL, CallMode::Direct),
            ),
        );

        self::assertStringContainsString('Ici', self::shownText($html));
        self::assertStringNotContainsString(
            (new MessagesXmlParser())->defaults()->get(FrameworkMessage::WebLeave),
            self::shownText($html),
        );
    }

    public function testNavigatesTheService(): void
    {
        $routes = new RouterBuilder('home');
        $routes->addRoute('home', new CallbackController(NoParameters::class, static fn(
            Request $request,
        ) => $request->functionKey() === FunctionKey::Send
            ? Response::goTo('games')
            : Response::display(Vdt::clearScreen()->append(Vdt::position(1, row: 1), Vdt::text('HOME')))));
        $routes->addRoute('games', new CallbackController(NoParameters::class, static fn(
            Request $request,
        ) => Response::display(Vdt::clearScreen()->append(
            Vdt::position(1, row: 1),
            Vdt::text('GAMES ' . $request->contentAsString()),
        ))));
        $router = $routes->build();
        $runner = new ServiceRunner(
            new Service(
                $router,
                (new MessagesXmlParser())->defaults(),
                new ContextSerializer($router, new HmacContextSigner('secret')),
                logger: new MemoryLogger(),
            ),
            self::client(),
        );

        $home = (string) $runner->handle(HttpRequests::to('Mozilla/5.0', ['web' => 'true']))->getBody();
        self::assertStringContainsString('HOME', self::shownText($home));

        // Envoi on the home page, with its context and screen
        $context = [];
        $screen = [];
        preg_match('#name="context" value="([^"]*)"#', $home, $context);
        preg_match('#name="screen" value="([^"]*)"#', $home, $screen);
        $games = (string) $runner
            ->handle(HttpRequests::posting(
                'Mozilla/5.0',
                ['web' => 'true'],
                [
                    'key' => 'ENVOI',
                    'content' => ['2'],
                    'context' => html_entity_decode($context[1] ?? ''),
                    'screen' => $screen[1] ?? '',
                ],
            ))
            ->getBody();

        self::assertStringContainsString('GAMES', self::shownText($games));
        self::assertStringNotContainsString('HOME', self::shownText($games));
    }

    private static function client(?Messages $messages = null): HtmlClient
    {
        $messages ??= (new MessagesXmlParser())->defaults();

        return new HtmlClient(new HtmlForm(new HtmlRenderer(HtmlRenderer::MINITEL_FONT), $messages), $messages);
    }

    /**
     * Page the client gives for a response, to a form posted with values: the request is read
     * by the client, with its state, as ServiceRunner does
     *
     * @param array<string, mixed> $form
     */
    private static function page(GatewayResponse $response, array $form = [], ?Messages $messages = null): string
    {
        $exchange = self::client($messages)
            ->exchange(HttpRequests::posting('Mozilla/5.0', ['web' => 'true'], $form), self::SERVICE_URL);

        return (string) $exchange->respond($response)->getBody();
    }

    /**
     * The hidden values of a page, as the browser posts them back
     *
     * @return array<string, string>
     */
    private static function hiddenValues(string $html): array
    {
        $matches = [];
        preg_match_all('#<input type="hidden" name="(\w+)" value="([^"]*)">#', $html, $matches, PREG_SET_ORDER);

        return array_column(
            array_map(static fn(array $match) => [
                $match[1],
                html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5),
            ], $matches),
            1,
            0,
        );
    }

    /**
     * Text the page shows: its cells, without the markup, the style and the hidden values
     */
    private static function shownText(string $html): string
    {
        $html = (string) preg_replace(
            ['#<style>.*</style>#s', '#<input[^>]*>#', '#<div class="keys">.*</div>#s'],
            replacement: '',
            subject: $html,
        );

        return html_entity_decode(strip_tags($html));
    }

    /**
     * A command calling another service, which the HTML view blocks
     */
    private static function leaving(): ServiceCallCommand
    {
        return new ServiceCallCommand('https://example.org/', CallMode::Connect);
    }
}
