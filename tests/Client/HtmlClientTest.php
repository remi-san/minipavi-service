<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Client;

use MiniPaviService\Client\HtmlClient;
use MiniPaviService\Controller\Parameters\NoParameters;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;
use MiniPaviService\Core\Router;
use MiniPaviService\Core\Service;
use MiniPaviService\Gateway\GatewayAnswer;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Http\ServiceRunner;
use MiniPaviService\Message\FrameworkMessage;
use MiniPaviService\Message\Messages;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Page\InputCommand;
use MiniPaviService\Tests\Support\CallbackController;
use MiniPaviService\Tests\Support\HttpRequests;
use MiniPaviService\Tests\Support\MemoryLogger;
use MiniPaviService\Vdt\HtmlRenderer;
use MiniPaviService\Vdt\Vdt;
use MiniPaviService\Vdt\VdtHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

final class HtmlClientTest extends TestCase
{
    private const SERVICE_URL = 'http://services/Svc/index.php';

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function connections(): iterable
    {
        yield 'no route' => [['web' => 'true']];
        yield 'an empty route' => [['web' => 'true', 'route' => '']];
    }

    /**
     * @param array<string, string> $query
     */
    #[DataProvider('connections')]
    public function testFirstRequestIsAConnection(array $query): void
    {
        self::assertEquals(
            new GatewayRequest(GatewayRequest::FCTN_CONNECT, serviceUrl: self::SERVICE_URL),
            self::client()->request(HttpRequests::to('Mozilla/5.0', $query), self::SERVICE_URL),
        );
    }

    public function testFirstRequestOnARoute(): void
    {
        // A user who just got there: no key, no input
        self::assertEquals(
            new GatewayRequest('', [], '{"route":"games"}', serviceUrl: self::SERVICE_URL),
            self::client()->request(HttpRequests::to('Mozilla/5.0', ['web' => 'true', 'route' => 'games']), self::SERVICE_URL),
        );
    }

    public function testKeyPressedWithWhatWasTyped(): void
    {
        $form = ['key' => 'ENVOI', 'content' => ['36', '15'], 'context' => '{"route":"menu"}', 'screen' => ''];

        self::assertEquals(
            new GatewayRequest('ENVOI', ['36', '15'], '{"route":"menu"}', serviceUrl: self::SERVICE_URL),
            self::client()->request(HttpRequests::posting('Mozilla/5.0', ['web' => 'true', 'route' => 'games'], $form), self::SERVICE_URL),
        );
    }

    public function testUnknownKeyIsAConnection(): void
    {
        self::assertSame(GatewayRequest::FCTN_CONNECT, self::client()->request(HttpRequests::posting('Mozilla/5.0', ['web' => 'true'], ['key' => 'CNX']), self::SERVICE_URL)->key);
    }

    public function testPageKeepsTheContextAndTheScreen(): void
    {
        $vdt = VdtHelper::clearScreen()->append(VdtHelper::setPos(1, 1), Vdt::text('Bonjour <3'));
        $html = self::page(new GatewayAnswer($vdt, '{"route":"home"}'));

        self::assertStringStartsWith('<!doctype html>', $html);
        self::assertStringContainsString('<form method="post" class="page">', $html);
        self::assertStringContainsString('<title>' . self::SERVICE_URL . '</title>', $html);
        self::assertStringContainsString('Bonjour <3', self::shownText($html));
        self::assertStringContainsString('<input type="hidden" name="context" value="{&quot;route&quot;:&quot;home&quot;}">', $html);
        // The screen from its clear screen (FF)
        self::assertStringContainsString('<input type="hidden" name="screen" value="' . base64_encode($vdt->fromLastClear()->toString()) . '">', $html);
    }

    public function testAnswerIsDrawnOverTheScreenSoFar(): void
    {
        $before = VdtHelper::clearScreen()->append(VdtHelper::setPos(1, 3), Vdt::text('Page 2/2'));

        $html = self::page(new GatewayAnswer(VdtHelper::writeLine0('Dernière page'), '{}'), ['screen' => base64_encode($before->toString())]);

        self::assertStringContainsString('Page 2/2', self::shownText($html));
        self::assertStringContainsString('Dernière page', self::shownText($html));
    }

    public function testScreenIsKeptFromItsLastClear(): void
    {
        $before = VdtHelper::clearScreen()->append(VdtHelper::setPos(1, 3), Vdt::text('Avant'));
        $answer = VdtHelper::clearScreen()->append(VdtHelper::setPos(1, 3), Vdt::text('Après'));

        $html = self::page(new GatewayAnswer($answer, '{}'), ['screen' => base64_encode($before->toString())]);

        self::assertStringContainsString('<input type="hidden" name="screen" value="' . base64_encode($answer->fromLastClear()->toString()) . '">', $html);
        self::assertStringNotContainsString('Avant', self::shownText($html));
    }

    public function testTextFieldOverTheScreen(): void
    {
        $html = self::page(new GatewayAnswer(new Vdt(), '{}', command: InputCommand::text(16, 24, 2, FunctionKey::masks(FunctionKey::Send), prefill: 'ab')->toArray()));

        self::assertSame(1, substr_count($html, 'class="field"'));
        self::assertStringContainsString('name="content[]" maxlength="2" value="ab" style="' . HtmlRenderer::at(16, 24, 2) . '" autofocus', $html);
    }

    public function testMessageFieldHasAnInputPerLine(): void
    {
        $html = self::page(new GatewayAnswer(new Vdt(), '{}', command: InputCommand::message(1, 13, 40, 2, FunctionKey::masks(FunctionKey::Send), prefill: ['l1', 'l2'])->toArray()));

        self::assertStringContainsString('value="l1" style="' . HtmlRenderer::at(1, 13, 40) . '"', $html);
        self::assertStringContainsString('value="l2" style="' . HtmlRenderer::at(1, 14, 40) . '"', $html);
    }

    public function testKeysTheInputDoesntAcceptAreDisabled(): void
    {
        $html = self::page(new GatewayAnswer(new Vdt(), '{}', command: InputCommand::text(1, 1, 1, FunctionKey::masks(FunctionKey::Send, FunctionKey::Next))->toArray()));

        self::assertSame(8, substr_count($html, 'name="key"'));
        self::assertSame(6, substr_count($html, ' disabled>'));
        self::assertMatchesRegularExpression('#value="SUITE" style="order: \d+">Suite</button>#', $html);
        // Envoi first: the key Enter presses in a field
        self::assertMatchesRegularExpression('#<div class="keys"><button type="submit" name="key" value="ENVOI"#', $html);
    }

    public function testWithoutInputEveryKeyIsEnabled(): void
    {
        $html = self::page(new GatewayAnswer(new Vdt(), '{}'));

        self::assertSame(8, substr_count($html, 'name="key"'));
        self::assertStringNotContainsString(' disabled>', $html);
        self::assertStringNotContainsString('class="field"', $html);
    }

    public function testKeyLabelsAreMessages(): void
    {
        $html = self::page(new GatewayAnswer(new Vdt(), '{}'), messages: Messages::defaults()->with(['key.send' => 'Valider']));

        self::assertStringContainsString('value="ENVOI" style="order: 7">Valider</button>', $html);
    }

    public function testLeavingTheServiceIsBlocked(): void
    {
        // The page the key is pressed on: its hidden values, posted back as they are
        $before = VdtHelper::clearScreen()->append(VdtHelper::setPos(1, 3), Vdt::text('Annuaire'));
        $page = self::page(new GatewayAnswer($before, '{"route":"directory"}', command: InputCommand::text(7, 23, 23, FunctionKey::masks(FunctionKey::Send))->toArray()));
        $form = ['key' => 'ENVOI'] + self::hiddenValues($page);

        $html = self::page(new GatewayAnswer(VdtHelper::writeLine0('Cnx en cours'), '{"route":"elsewhere"}', 'https://example.org/'), $form, new GatewayRequest('ENVOI', [], '{"route":"directory"}', serviceUrl: self::SERVICE_URL));

        $shown = self::shownText($html);
        self::assertStringContainsString('Annuaire', $shown);
        self::assertStringContainsString(Messages::defaults()->get(FrameworkMessage::WebLeave), $shown);
        self::assertStringNotContainsString('Cnx en cours', $shown);
        // Where the user was: their context and their input
        self::assertStringContainsString('name="context" value="{&quot;route&quot;:&quot;directory&quot;}"', $html);
        self::assertStringContainsString('style="' . HtmlRenderer::at(7, 23, 23) . '"', $html);
        self::assertSame(7, substr_count($html, ' disabled>'));
    }

    public function testNextUrlOfTheServiceIsntLeaving(): void
    {
        $html = self::page(new GatewayAnswer(VdtHelper::setPos(1, 1)->append(Vdt::text('Ici')), '{}', self::SERVICE_URL));

        self::assertStringContainsString('Ici', self::shownText($html));
        self::assertStringNotContainsString(Messages::defaults()->get(FrameworkMessage::WebLeave), self::shownText($html));
    }

    public function testNavigatesTheService(): void
    {
        $router = new Router();
        $router->addRoute('home', new CallbackController(NoParameters::class, fn(Request $request) => $request->functionKey() === FunctionKey::Send
            ? Response::goTo('games')
            : Response::display(VdtHelper::clearScreen()->append(VdtHelper::setPos(1, 1), Vdt::text('HOME')))));
        $router->addRoute('games', new CallbackController(NoParameters::class, fn(Request $request) => Response::display(VdtHelper::clearScreen()->append(VdtHelper::setPos(1, 1), Vdt::text('GAMES ' . $request->contentAsString())))));
        $router->setDefaultRoute('home');
        $runner = new ServiceRunner(new Service($router, Messages::defaults(), new MemoryLogger()), self::client());

        $home = (string) $runner->handle(HttpRequests::to('Mozilla/5.0', ['web' => 'true']))->getBody();
        self::assertStringContainsString('HOME', self::shownText($home));

        // Envoi on the home page, with its context and screen
        preg_match('#name="context" value="([^"]*)"#', $home, $context);
        preg_match('#name="screen" value="([^"]*)"#', $home, $screen);
        $games = (string) $runner->handle(HttpRequests::posting('Mozilla/5.0', ['web' => 'true'], [
            'key' => 'ENVOI', 'content' => ['2'], 'context' => html_entity_decode($context[1] ?? ''), 'screen' => $screen[1] ?? '',
        ]))->getBody();

        self::assertStringContainsString('GAMES', self::shownText($games));
        self::assertStringNotContainsString('HOME', self::shownText($games));
    }

    public function testUnknownRouteShowsTheFirstScreenAsTheServiceDoes(): void
    {
        $router = new Router();
        $router->addRoute('home', new CallbackController(NoParameters::class, fn(Request $request) => Response::display(VdtHelper::setPos(1, 1)->append(Vdt::text('HOME')))));
        $router->setDefaultRoute('home');
        $logger = new MemoryLogger();

        $html = (string) (new ServiceRunner(new Service($router, Messages::defaults(), $logger), self::client()))->handle(HttpRequests::to('Mozilla/5.0', ['web' => 'true', 'route' => 'nope']))->getBody();

        self::assertStringContainsString('HOME', self::shownText($html));
        self::assertSame([LogLevel::WARNING, 'Unknown route in the context, starting again at the default route', ['route' => 'nope']], $logger->records[0]);
    }

    private static function client(?Messages $messages = null): HtmlClient
    {
        return new HtmlClient(new HtmlRenderer(HtmlRenderer::MINITEL_FONT), $messages ?? Messages::defaults());
    }

    /**
     * Page the client gives for an answer, to a form posted with values
     *
     * @param array<string, mixed> $form
     */
    private static function page(GatewayAnswer $answer, array $form = [], ?GatewayRequest $request = null, ?Messages $messages = null): string
    {
        $httpRequest = HttpRequests::posting('Mozilla/5.0', ['web' => 'true'], $form);
        $request ??= new GatewayRequest(GatewayRequest::FCTN_CONNECT, serviceUrl: self::SERVICE_URL);

        return (string) self::client($messages)->response($httpRequest, $request, $answer)->getBody();
    }

    /**
     * The hidden values of a page, as the browser posts them back
     *
     * @return array<string, string>
     */
    private static function hiddenValues(string $html): array
    {
        preg_match_all('#<input type="hidden" name="(\w+)" value="([^"]*)">#', $html, $matches, PREG_SET_ORDER);

        return array_column(array_map(fn(array $match) => [$match[1], html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5)], $matches), 1, 0);
    }

    /**
     * Text the page shows: its cells, without the markup, the style and the hidden values
     */
    private static function shownText(string $html): string
    {
        $html = (string) preg_replace(['#<style>.*</style>#s', '#<input[^>]*>#', '#<div class="keys">.*</div>#s'], '', $html);

        return html_entity_decode(strip_tags($html));
    }
}
