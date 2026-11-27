<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Client\Html;

use DOMDocument;
use DOMElement;
use DOMXPath;
use MiniPaviService\Client\Html\HtmlClient;
use MiniPaviService\Client\Html\HtmlForm;
use MiniPaviService\Client\Html\HtmlRenderer;
use MiniPaviService\Controller\Parameters\NoParameters;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response\ChangePage;
use MiniPaviService\Controller\Response\DisplayPage;
use MiniPaviService\Core\Context\ContextSerializer;
use MiniPaviService\Core\Context\Signing\HmacContextSigner;
use MiniPaviService\Core\Routing\RouterBuilder;
use MiniPaviService\Core\Service;
use MiniPaviService\Core\Sessions;
use MiniPaviService\Gateway\Command\CallMode;
use MiniPaviService\Gateway\Command\InputCommand;
use MiniPaviService\Gateway\Command\ServiceCallCommand;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\Http\ServiceRunner;
use MiniPaviService\Message\FrameworkMessage;
use MiniPaviService\Message\Messages;
use MiniPaviService\Message\MessagesXmlParser;
use MiniPaviService\Minitel\ConnectionCode;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\InputField;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\Span;
use MiniPaviService\Tests\Support\CallbackController;
use MiniPaviService\Tests\Support\HttpRequests;
use MiniPaviService\Tests\Support\MemoryLogger;
use MiniPaviService\Vdt\ClearScreens;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The HTML view as the browser gets it: each page is parsed, and what is checked is what the
 * user sees and posts back (the screen's text, the fields and where they are, the keys), not
 * how the markup is written
 */
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
        $screen = Vdt::position(new Position(row: 1))->append(Vdt::text('Menu'));
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
        // Placed over its cell by that style alone (what it places: HtmlRendererTest)
        self::assertSame(
            [['value' => 'ab', 'style' => self::place(new Position(row: 2), length: 4)]],
            self::fields($html),
        );
        // Underlined without a border, which would take a pixel of the cell from its text
        $rules = self::cssRules($html);
        self::assertSame('0', $rules['.field']['border'] ?? null);
        foreach ($rules as $selector => $declarations) {
            if (!str_starts_with($selector, '.field')) {
                continue;
            }
            self::assertSame([], array_filter(array_keys($declarations), static fn(string $property) => str_starts_with(
                $property,
                'border-',
            )), $selector);
        }
        // Its keys: Envoi and Suite only
        self::assertSame(['SUITE', 'ENVOI'], self::enabledKeys($html));
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
        self::assertSame([], self::fields($html));
    }

    public function testARequestItCouldntReadGetsABlankPageOfThisService(): void
    {
        $html = (string) self::client()
            ->unreadable('http://services/Other/index.php')
            ->respond(new GatewayResponse(Vdt::line0('Erreur'), '{}'))
            ->getBody();

        self::assertSame('http://services/Other/index.php', self::title($html));
        self::assertStringContainsString('Erreur', self::shownText($html));
        // No field: no page was posted
        self::assertSame([], self::fields($html));
    }

    public function testConnectionIsRespondedToAtTheUrlItWasSentTo(): void
    {
        $html = (string) self::client()
            ->exchange(HttpRequests::to('Mozilla/5.0', [
                'web' => 'true',
            ]), serviceUrl: 'http://services/Other/index.php')
            ->respond(new GatewayResponse(new Vdt(), '{}'))
            ->getBody();

        self::assertSame('http://services/Other/index.php', self::title($html));
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
        $vdt = Vdt::clearScreen()->append(Vdt::position(new Position(row: 1)), Vdt::text('Bonjour <3'));
        $html = self::page(new GatewayResponse($vdt, '{"route":"home"}'));

        // A standards mode page, posting its form back to the service
        self::assertStringStartsWith('<!doctype html>', $html);
        self::assertSame(['post'], self::values($html, query: '//form/@method'));
        self::assertSame(self::SERVICE_URL, self::title($html));
        self::assertStringContainsString('Bonjour <3', self::shownText($html));
        // The screen from its clear screen (FF)
        self::assertSame(
            [
                'context' => '{"route":"home"}',
                'command' => '',
                'screen' => base64_encode(
                    (new ClearScreens())
                        ->fromLast($vdt)
                        ->toString(),
                ),
            ],
            self::hiddenValues($html),
        );
    }

    public function testPageIsAnHtmlResponse(): void
    {
        $response = self::client()
            ->exchange(HttpRequests::to('Mozilla/5.0', ['web' => 'true']), self::SERVICE_URL)
            ->respond(new GatewayResponse(new Vdt(), '{}'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['text/html; charset=utf-8'], $response->getHeader('Content-Type'));
    }

    public function testPageIsSelfContainedWithTheMinitelFont(): void
    {
        $html = self::page(new GatewayResponse(new Vdt(), ''));

        self::assertCount(1, self::nodes($html, query: self::screen()));
        self::assertStringContainsString(
            '@font-face { font-family: Minitel; src: url(data:font/ttf;base64,'
            . base64_encode((string) file_get_contents(HtmlRenderer::MINITEL_FONT))
            . ') format("truetype"); }',
            self::stylesheet($html),
        );
        self::assertCount(0, self::nodes($html, query: '//script | //link'));
    }

    public function testTitleOfThePageIsShownAsItIsGiven(): void
    {
        $messages = (new MessagesXmlParser())->defaults();
        $form = new HtmlForm(new HtmlRenderer(HtmlRenderer::MINITEL_FONT), $messages);

        $html = $form->render(new Vdt(), context: '', command: null, title: 'Svc <a & "b">');

        self::assertSame('Svc <a & "b">', self::title($html));
        // No markup injected
        self::assertCount(0, self::nodes($html, query: '//title/* | //a'));
    }

    public function testResponseIsDrawnOverTheScreenSoFar(): void
    {
        $before = Vdt::clearScreen()->append(Vdt::position(new Position(row: 3)), Vdt::text('Page 2/2'));

        $html = self::page(new GatewayResponse(Vdt::line0('Dernière page'), '{}'), [
            'key' => 'SUITE',
            'screen' => base64_encode($before->toString()),
        ]);

        self::assertStringContainsString('Page 2/2', self::shownText($html));
        self::assertStringContainsString('Dernière page', self::shownText($html));
    }

    public function testScreenIsKeptFromItsLastClear(): void
    {
        $before = Vdt::clearScreen()->append(Vdt::position(new Position(row: 3)), Vdt::text('Avant'));
        $response = Vdt::clearScreen()->append(Vdt::position(new Position(row: 3)), Vdt::text('Après'));

        $html = self::page(new GatewayResponse($response, '{}'), [
            'key' => 'SUITE',
            'screen' => base64_encode($before->toString()),
        ]);

        self::assertSame(
            base64_encode(
                (new ClearScreens())
                    ->fromLast($response)
                    ->toString(),
            ),
            self::hiddenValues($html)['screen'] ?? null,
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

        self::assertSame(
            [['value' => 'ab', 'style' => self::place(new Position(row: 24, col: 16), length: 2)]],
            self::fields($html),
        );
        // What the user types in it (posted as the content, as fields() finds it): as long as
        // the field, typed in at once
        self::assertSame(['2'], self::values($html, query: self::fieldInputs() . '/@maxlength'));
        self::assertCount(1, self::nodes($html, query: self::fieldInputs() . '[@autofocus]'));
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

        self::assertSame(
            [
                ['value' => 'l1', 'style' => self::place(new Position(row: 13), length: 40)],
                ['value' => 'l2', 'style' => self::place(new Position(row: 14), length: 40)],
            ],
            self::fields($html),
        );
        // The first line typed in at once
        self::assertSame(['l1'], self::values($html, query: self::fieldInputs() . '[@autofocus]/@value'));
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

        self::assertSame(['SUITE', 'ENVOI'], self::enabledKeys($html));
        // Envoi first in the form: the key Enter presses in a field
        self::assertSame('ENVOI', self::keys($html)[0]->getAttribute('value'));
        // Shown in the Minitel's layout, whatever their order in the form
        self::assertSame(
            array_map(static fn(FunctionKey $key) => $key->value, FunctionKey::cases()),
            self::keysAsShown($html),
        );
    }

    public function testWithoutInputEveryKeyIsEnabled(): void
    {
        $html = self::page(new GatewayResponse(new Vdt(), '{}'));

        self::assertSame(
            array_map(static fn(FunctionKey $key) => $key->value, FunctionKey::cases()),
            self::keysAsShown($html),
        );
        self::assertCount(8, self::enabledKeys($html));
        self::assertSame([], self::fields($html));
    }

    public function testKeyLabelsAreMessages(): void
    {
        $html = self::page(new GatewayResponse(new Vdt(), '{}'), messages: (new MessagesXmlParser())
            ->defaults()
            ->with([
                'key.send' => 'Valider',
            ]));

        self::assertSame(['Valider'], self::values($html, query: '//form//button[@name="key"][@value="ENVOI"]'));
    }

    public function testLeavingTheServiceIsBlocked(): void
    {
        // The page the key is pressed on: its hidden values, posted back as they are
        $before = Vdt::clearScreen()->append(Vdt::position(new Position(row: 3)), Vdt::text('Annuaire'));
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
        self::assertSame('{"route":"directory"}', self::hiddenValues($html)['context'] ?? null);
        self::assertSame(
            [['value' => '', 'style' => self::place(new Position(row: 23, col: 7), length: 23)]],
            self::fields($html),
        );
        self::assertSame(['ENVOI'], self::enabledKeys($html));
    }

    public function testCallingTheServiceIsntLeaving(): void
    {
        $html = self::page(
            new GatewayResponse(
                Vdt::position(new Position(row: 1))->append(Vdt::text('Ici')),
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
            ? new ChangePage('games')
            : new DisplayPage(Vdt::clearScreen()->append(Vdt::position(new Position(row: 1)), Vdt::text('HOME')))));
        $routes->addRoute(
            'games',
            new CallbackController(
                NoParameters::class,
                static fn(Request $request) => new DisplayPage(Vdt::clearScreen()->append(
                    Vdt::position(new Position(row: 1)),
                    Vdt::text('GAMES ' . $request->contentAsString()),
                )),
            ),
        );
        $router = $routes->build();
        $runner = new ServiceRunner(
            new Service(
                new Sessions(new ContextSerializer($router, new HmacContextSigner('secret')), $router),
                (new MessagesXmlParser())->defaults(),
                logger: new MemoryLogger(),
            ),
            self::client(),
        );

        $home = (string) $runner->handle(HttpRequests::to('Mozilla/5.0', ['web' => 'true']))->getBody();
        self::assertStringContainsString('HOME', self::shownText($home));

        // Envoi on the home page, with its hidden values
        $games = (string) $runner->handle(HttpRequests::posting('Mozilla/5.0', ['web' => 'true'], [
            'key' => 'ENVOI',
            'content' => ['2'],
        ] + self::hiddenValues($home)))->getBody();

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
     * The nodes of the page, as the browser reads it, that the XPath query finds
     *
     * @return list<\DOMNode>
     */
    private static function nodes(string $html, string $query): array
    {
        $document = new DOMDocument();
        // HTML5 elements libxml doesn't know aren't errors
        $document->loadHTML($html, LIBXML_NOERROR);
        $nodes = (new DOMXPath($document))->query($query);
        if ($nodes === false) {
            self::fail("Invalid query: {$query}");
        }

        return iterator_to_array($nodes, preserve_keys: false);
    }

    /**
     * Text of each node the query finds (an attribute's value, an element's text)
     *
     * @return list<string>
     */
    private static function values(string $html, string $query): array
    {
        return array_map(static fn(\DOMNode $node) => $node->textContent, self::nodes($html, $query));
    }

    /**
     * The title of the page, in the browser
     */
    private static function title(string $html): string
    {
        return implode('', self::values($html, query: '//title'));
    }

    /**
     * The hidden values of a page, as the browser posts them back
     *
     * @return array<string, string>
     */
    private static function hiddenValues(string $html): array
    {
        $values = [];
        foreach (self::elements($html, query: '//form//input[@type="hidden"]') as $input) {
            $values[$input->getAttribute('name')] = $input->getAttribute('value');
        }

        return $values;
    }

    /**
     * Text the page shows: its screen's cells, without the fields over it
     */
    private static function shownText(string $html): string
    {
        return implode('', self::values($html, query: self::screen()));
    }

    /**
     * XPath of the screen: the element of class minitel, whatever its other classes
     */
    private static function screen(): string
    {
        return '//div[contains(concat(" ", normalize-space(@class), " "), " minitel ")]';
    }

    /**
     * XPath of the input fields, wherever they are on the page: what the form posts as the
     * content, in its order
     */
    private static function fieldInputs(): string
    {
        return '//input[@name="content[]"]';
    }

    /**
     * XPath of the input fields outside the screen
     */
    private static function fieldsOutsideTheScreen(): string
    {
        return (
            self::fieldInputs()
            . '[not(ancestor::div[contains(concat(" ", normalize-space(@class), " "), " minitel ")])]'
        );
    }

    /**
     * The input fields over the screen, in the form's order: what each shows, and the style
     * placing it. Every field of the form is one: in the screen, the container its style places
     * it against (a field elsewhere would be placed against the page, not over its cell)
     *
     * @return list<array{value: string, style: string}>
     */
    private static function fields(string $html): array
    {
        self::assertCount(0, self::nodes($html, query: self::fieldsOutsideTheScreen()));

        return array_map(
            static fn(DOMElement $input) => [
                'value' => $input->getAttribute('value'),
                'style' => $input->getAttribute('style'),
            ],
            self::elements($html, query: self::fieldInputs()),
        );
    }

    /**
     * Style placing a field over the screen
     */
    private static function place(Position $position, int $length): string
    {
        return (new HtmlRenderer(HtmlRenderer::MINITEL_FONT))->place($position, $length);
    }

    /**
     * The function keys' buttons, in the form's order. Every key of the form is one: an item of
     * the keys' grid, which lays them out by their CSS order and styles the disabled ones (a key
     * elsewhere would show in the form's order, without either)
     *
     * @return list<DOMElement>
     */
    private static function keys(string $html): array
    {
        $keys = '//form//button[@type="submit"][@name="key"]';
        self::assertCount(0, self::nodes(
            $html,
            query: $keys . '[not(parent::*[contains(concat(" ", normalize-space(@class), " "), " keys ")])]',
        ));

        return self::elements($html, query: $keys);
    }

    /**
     * The keys the user can press, in the order they are shown
     *
     * @return list<string>
     */
    private static function enabledKeys(string $html): array
    {
        return array_values(array_intersect(
            self::keysAsShown($html),
            array_map(
                static fn(DOMElement $button) => $button->getAttribute('value'),
                array_filter(self::keys($html), static fn(DOMElement $button) => !$button->hasAttribute('disabled')),
            ),
        ));
    }

    /**
     * The keys, in the order the grid shows them: by their CSS order
     *
     * @return list<string>
     */
    private static function keysAsShown(string $html): array
    {
        $shown = [];
        foreach (self::keys($html) as $button) {
            $order = self::declarations($button->getAttribute('style'))['order'] ?? self::fail('A key without order');
            $shown[(int) $order] = $button->getAttribute('value');
        }
        ksort($shown);

        return array_values($shown);
    }

    /**
     * The page's stylesheet
     */
    private static function stylesheet(string $html): string
    {
        return implode('', self::values($html, query: '//head/style'));
    }

    /**
     * The declarations of the stylesheet's rules, by selector (each of a list), then property
     *
     * @return array<string, array<string, string>>
     */
    private static function cssRules(string $html): array
    {
        $rules = [];
        preg_match_all('/([^{}]+)\{([^}]*)\}/', self::stylesheet($html), $rules, PREG_SET_ORDER);
        $declarations = [];
        foreach ($rules as [, $selectors, $body]) {
            foreach (explode(',', $selectors) as $selector) {
                $declarations[trim($selector)] = self::declarations($body) + ($declarations[trim($selector)] ?? []);
            }
        }

        return $declarations;
    }

    /**
     * CSS declarations (a style attribute, a rule's body), by property
     *
     * @return array<string, string>
     */
    private static function declarations(string $css): array
    {
        $declarations = [];
        foreach (explode(';', $css) as $declaration) {
            if (!str_contains($declaration, ':')) {
                continue;
            }
            [$property, $value] = explode(':', $declaration, limit: 2);
            $declarations[trim($property)] = trim($value);
        }

        return $declarations;
    }

    /**
     * @return list<DOMElement>
     */
    private static function elements(string $html, string $query): array
    {
        $elements = [];
        foreach (self::nodes($html, $query) as $node) {
            self::assertInstanceOf(DOMElement::class, $node);
            $elements[] = $node;
        }

        return $elements;
    }

    /**
     * A command calling another service, which the HTML view blocks
     */
    private static function leaving(): ServiceCallCommand
    {
        return new ServiceCallCommand('https://example.org/', CallMode::Connect);
    }
}
