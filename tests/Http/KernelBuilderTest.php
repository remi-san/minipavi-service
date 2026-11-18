<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Http;

use MiniPaviService\Controller\Parameters\NoParameters;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;
use MiniPaviService\Core\Router;
use MiniPaviService\Core\Signing\HmacContextSigner;
use MiniPaviService\Http\Dispatch\ConditionalRequestHandler;
use MiniPaviService\Http\Dispatch\QueryParameterEquals;
use MiniPaviService\Http\Emulator\EmulatorConfig;
use MiniPaviService\Http\KernelBuilder;
use MiniPaviService\Http\Url\RequestUrl;
use MiniPaviService\Message\Messages;
use MiniPaviService\Tests\Support\CallbackController;
use MiniPaviService\Tests\Support\MemoryLogger;
use MiniPaviService\Tests\Support\RecordingHandler;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

/**
 * Runs the Kernel built, on requests set in the globals, and reads what it sends: in separate
 * processes. From the command line, PHP keeps the status code, but sends no headers.
 */
final class KernelBuilderTest extends TestCase
{
    private KernelBuilder $builder;

    protected function setUp(): void
    {
        $router = new Router();
        $router->addRoute('home', new CallbackController(NoParameters::class, static fn(
            Request $request,
        ) => Response::display(Vdt::position(1, row: 1)->append(Vdt::text('HOME')))));
        $router->setDefaultRoute('home');
        $this->builder = KernelBuilder::for($router, Messages::defaults());
    }

    #[RunInSeparateProcess]
    public function testBrowsersAskingWebGetTheServiceWithASignedContext(): void
    {
        $html = self::serve($this->builder->withContextKey('secret'), query: 'web=true');

        $context = [];
        preg_match('#name="context" value="([^"]*)"#', $html, $context);
        self::assertSame(
            '{"route":"home","states":{"home":"read-input"},"parameters":{"home":{}}}',
            (new HmacContextSigner('secret'))->verify(html_entity_decode($context[1] ?? '')),
        );
        // The page's title is the service's URL, from the request
        self::assertStringContainsString('<title>http://example.org/Svc/index.php', $html);
    }

    #[RunInSeparateProcess]
    public function testOtherBrowsersAreSentToTheEmulator(): void
    {
        $page = self::serve(
            $this->builder->withEmulator(new EmulatorConfig(
                'http://localhost:8081/',
                'ws://localhost:8182/',
                'services',
            )),
            query: '',
        );

        self::assertSame(302, http_response_code());
        self::assertSame('', $page);
    }

    #[RunInSeparateProcess]
    public function testHandlersOfTheServiceAreTriedFirst(): void
    {
        $api = new RecordingHandler('api');

        $page = self::serve(
            $this->builder->withHandler(
                new ConditionalRequestHandler('api', new QueryParameterEquals('web', 'true'), $api),
            ),
            query: 'web=true',
        );

        self::assertSame('api', $page);
        self::assertCount(1, $api->requests);
    }

    #[RunInSeparateProcess]
    public function testComputesTheUrlsWithTheRequestUrl(): void
    {
        $html = self::serve(
            $this->builder->withRequestUrl(new RequestUrl('https://minitel.example.org')),
            query: 'web=true',
        );

        self::assertStringContainsString('<title>https://minitel.example.org/Svc/index.php', $html);
    }

    #[RunInSeparateProcess]
    public function testLogsAndWarnsThatTheContextIsNotSignedWithoutKey(): void
    {
        $logger = new MemoryLogger();

        self::serve($this->builder->withLogger($logger), query: 'web=true');

        self::assertSame([LogLevel::WARNING, 'No key signing the context: it is not signed, and can be forged'], [
            $logger->records[0][0],
            $logger->records[0][1],
        ]);
        // Every part logs to it: the Dispatcher, the Service
        self::assertContains([LogLevel::INFO, 'Connection', ['fctn' => 'CNX']], $logger->records);
    }

    #[RunInSeparateProcess]
    public function testDoesNotWarnWithAKey(): void
    {
        $logger = new MemoryLogger();

        self::serve($this->builder->withLogger($logger)->withContextKey('secret'), query: 'web=true');

        self::assertSame(
            [],
            array_filter($logger->records, static fn(array $record) => $record[0] === LogLevel::WARNING),
        );
    }

    /**
     * Output of the Kernel for a browser's GET of /Svc/ with this query
     */
    private static function serve(KernelBuilder $builder, string $query): string
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';
        $_SERVER['HTTP_HOST'] = 'example.org';
        $_SERVER['PHP_SELF'] = '/Svc/index.php';
        $_SERVER['REQUEST_URI'] = '/Svc/' . ($query === '' ? '' : "?{$query}");
        $_SERVER['QUERY_STRING'] = $query;
        $get = [];
        parse_str($query, $get);
        $_GET = $get;

        ob_start();
        try {
            $builder->build()->run();
        } finally {
            $output = (string) ob_get_clean();
        }

        return $output;
    }
}
