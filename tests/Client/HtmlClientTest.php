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
use MiniPaviService\Message\Messages;
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
    public function testAsksForTheFirstScreenAsOnAConnection(array $query): void
    {
        self::assertEquals(
            new GatewayRequest(GatewayRequest::FCTN_CONNECT, serviceUrl: 'http://services/Svc/index.php'),
            (new HtmlClient(new HtmlRenderer(HtmlRenderer::MINITEL_FONT)))->request(HttpRequests::to('Mozilla/5.0', $query), 'http://services/Svc/index.php'),
        );
    }

    public function testAsksForTheScreenOfARoute(): void
    {
        // A user who just got there: no key, no input
        self::assertEquals(
            new GatewayRequest('', [], '{"route":"games"}', serviceUrl: 'http://services/Svc/index.php'),
            (new HtmlClient(new HtmlRenderer(HtmlRenderer::MINITEL_FONT)))->request(HttpRequests::to('Mozilla/5.0', ['web' => 'true', 'route' => 'games']), 'http://services/Svc/index.php'),
        );
    }

    public function testGivesTheScreenOfTheAnswerAsAPage(): void
    {
        $response = (new HtmlClient(new HtmlRenderer(HtmlRenderer::MINITEL_FONT)))->response(
            new GatewayRequest(GatewayRequest::FCTN_CONNECT, serviceUrl: 'http://services/Svc/index.php'),
            new GatewayAnswer(VdtHelper::setPos(1, 1)->append(Vdt::text('Bonjour <3')), '{}'),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $html = (string) $response->getBody();
        self::assertStringStartsWith('<!doctype html>', $html);
        self::assertStringContainsString('<title>http://services/Svc/index.php</title>', $html);
        self::assertStringContainsString('Bonjour <3', self::shownText($html));
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function screens(): iterable
    {
        yield 'the first screen' => [['web' => 'true'], 'HOME init'];
        yield "a route's, at its initial state" => [['web' => 'true', 'route' => 'games'], 'GAMES init'];
    }

    /**
     * @param array<string, string> $query
     */
    #[DataProvider('screens')]
    public function testRunsTheServiceForABrowser(array $query, string $shown): void
    {
        self::assertStringContainsString($shown, self::shownText($this->runForBrowser($query, new MemoryLogger())));
    }

    public function testUnknownRouteShowsTheFirstScreenAsTheServiceDoes(): void
    {
        $logger = new MemoryLogger();

        self::assertStringContainsString('HOME', self::shownText($this->runForBrowser(['web' => 'true', 'route' => 'nope'], $logger)));
        self::assertSame([LogLevel::WARNING, 'Unknown route in the context, starting again at the default route', ['route' => 'nope']], $logger->records[0]);
    }

    /**
     * Page a service (home, games) gives a browser asking query
     *
     * @param array<string, string> $query
     */
    private function runForBrowser(array $query, MemoryLogger $logger): string
    {
        $router = new Router();
        foreach (['home' => 'HOME', 'games' => 'GAMES'] as $route => $text) {
            $router->addRoute($route, new CallbackController(NoParameters::class, fn(Request $request) => Response::display(VdtHelper::setPos(1, 1)->append(Vdt::text($text . ' ' . $request->state()->value)))));
        }
        $router->setDefaultRoute('home');

        return (string) (new ServiceRunner(new Service($router, Messages::defaults(), $logger), new HtmlClient(new HtmlRenderer(HtmlRenderer::MINITEL_FONT))))->handle(HttpRequests::to('Mozilla/5.0', $query))->getBody();
    }

    /**
     * Text the page shows: its cells, without the markup and the style
     */
    private static function shownText(string $html): string
    {
        return html_entity_decode(strip_tags((string) preg_replace('#<style>.*</style>#s', '', $html)));
    }
}
