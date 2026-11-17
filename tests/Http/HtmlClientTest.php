<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Http;

use MiniPaviService\Controller\Parameters\NoParameters;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;
use MiniPaviService\Core\Router;
use MiniPaviService\Core\Service;
use MiniPaviService\Gateway\GatewayAnswer;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Http\HtmlClient;
use MiniPaviService\Http\RequestUrl;
use MiniPaviService\Http\ServiceRunner;
use MiniPaviService\Message\Messages;
use MiniPaviService\Tests\Support\CallbackController;
use MiniPaviService\Tests\Support\MemoryLogger;
use MiniPaviService\Vdt\Vdt;
use MiniPaviService\Vdt\VdtHelper;
use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

#[BackupGlobals(true)]
final class HtmlClientTest extends TestCase
{
    protected function setUp(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.org';
        $_SERVER['REQUEST_URI'] = '/Svc/?web=true';
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function connections(): iterable
    {
        yield 'no route' => ['web=true'];
        yield 'an empty route' => ['web=true&route='];
    }

    #[DataProvider('connections')]
    public function testAsksForTheFirstScreenAsOnAConnection(string $query): void
    {
        $_SERVER['QUERY_STRING'] = $query;

        self::assertEquals(new GatewayRequest(GatewayRequest::FCTN_CONNECT), (new HtmlClient(new RequestUrl()))->receive());
    }

    public function testAsksForTheScreenOfARoute(): void
    {
        $_SERVER['QUERY_STRING'] = 'web=true&route=games';

        // A user who just got there: no key, no input
        self::assertEquals(new GatewayRequest('', [], '{"route":"games"}'), (new HtmlClient(new RequestUrl()))->receive());
    }

    public function testShowsTheScreenOfTheAnswerAsAPage(): void
    {
        ob_start();
        try {
            (new HtmlClient(new RequestUrl()))->send(new GatewayAnswer(VdtHelper::setPos(1, 1)->append(Vdt::text('Bonjour <3')), '{}'));
        } finally {
            $html = (string) ob_get_clean();
        }

        self::assertStringStartsWith('<!doctype html>', $html);
        self::assertStringContainsString('<title>http://example.org/Svc/?web=true</title>', $html);
        self::assertStringContainsString('Bonjour <3', self::shownText($html));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function screens(): iterable
    {
        yield 'the first screen' => ['web=true', 'HOME init'];
        yield "a route's, at its initial state" => ['web=true&route=games', 'GAMES init'];
    }

    #[DataProvider('screens')]
    public function testRunsTheServiceForABrowser(string $query, string $shown): void
    {
        $_SERVER['QUERY_STRING'] = $query;

        self::assertStringContainsString($shown, self::shownText($this->runForBrowser(new MemoryLogger())));
    }

    public function testUnknownRouteShowsTheFirstScreenAsTheServiceDoes(): void
    {
        $_SERVER['QUERY_STRING'] = 'web=true&route=nope';
        $logger = new MemoryLogger();

        self::assertStringContainsString('HOME', self::shownText($this->runForBrowser($logger)));
        self::assertSame([LogLevel::WARNING, 'Unknown route in the context, starting again at the default route', ['route' => 'nope']], $logger->records[0]);
    }

    /**
     * Output of a service (home, games) run for a browser
     */
    private function runForBrowser(MemoryLogger $logger): string
    {
        $router = new Router();
        foreach (['home' => 'HOME', 'games' => 'GAMES'] as $route => $text) {
            $router->addRoute($route, new CallbackController(NoParameters::class, fn(Request $request) => Response::display(VdtHelper::setPos(1, 1)->append(Vdt::text($text . ' ' . $request->state()->value)))));
        }
        $router->setDefaultRoute('home');

        ob_start();
        try {
            (new ServiceRunner(new Service($router, Messages::defaults(), $logger), new HtmlClient(new RequestUrl())))->run();
        } finally {
            $html = (string) ob_get_clean();
        }

        return $html;
    }

    /**
     * Text the page shows: its cells, without the markup and the style
     */
    private static function shownText(string $html): string
    {
        return html_entity_decode(strip_tags((string) preg_replace('#<style>.*</style>#s', '', $html)));
    }
}
