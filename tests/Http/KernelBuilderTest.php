<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Http;

use MiniPaviService\Controller\Parameters\NoParameters;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response\ChangePage;
use MiniPaviService\Controller\Response\DisplayPage;
use MiniPaviService\Core\Context\Signing\HmacContextSigner;
use MiniPaviService\Core\Routing\RouterBuilder;
use MiniPaviService\Http\Dispatch\ConditionalRequestHandler;
use MiniPaviService\Http\Dispatch\QueryParameterEquals;
use MiniPaviService\Http\Emulator\EmulatorConfig;
use MiniPaviService\Http\KernelBuilder;
use MiniPaviService\Http\Url\RequestUrl;
use MiniPaviService\InvalidValueException;
use MiniPaviService\Message\MessagesXmlParser;
use MiniPaviService\Minitel\Position;
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
        $routes = new RouterBuilder('home');
        $routes->addRoute(
            'home',
            new CallbackController(
                NoParameters::class,
                static fn(Request $request) => new DisplayPage(Vdt::position(new Position(row: 1))->append(Vdt::text(
                    'HOME',
                ))),
            ),
        );
        $router = $routes->build();
        $this->builder = KernelBuilder::for($router, (new MessagesXmlParser())->defaults());
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

    /**
     * The emulator last given: logged, as the redirection's Location isn't kept from the command
     * line
     */
    #[RunInSeparateProcess]
    public function testTheEmulatorLastGivenIsUsed(): void
    {
        $logger = new MemoryLogger();
        self::serve(
            $this->builder
                ->withEmulator(new EmulatorConfig('http://first:8081/', 'ws://first:8182/', 'services'))
                ->withEmulator(new EmulatorConfig('http://second:8081/', 'ws://second:8182/', 'services'))
                ->withLogger($logger),
            query: '',
        );

        $urls = array_column(
            array_filter($logger->records, static fn(array $record) => $record[1] === 'Browser sent to the emulator'),
            2,
        );
        self::assertCount(1, $urls);
        self::assertStringStartsWith('http://second:8081/', (string) ($urls[0]['url'] ?? ''));
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
    public function testEveryHandlerGivenIsKept(): void
    {
        $page = self::serve(
            $this->builder
                ->withHandler(
                    new ConditionalRequestHandler(
                        'first',
                        new QueryParameterEquals('api', 'first'),
                        new RecordingHandler('first'),
                    ),
                )
                ->withHandler(
                    new ConditionalRequestHandler(
                        'second',
                        new QueryParameterEquals('api', 'second'),
                        new RecordingHandler('second'),
                    ),
                ),
            query: 'api=first',
        );

        self::assertSame('first', $page);
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

    #[RunInSeparateProcess]
    public function testTheServiceAllowsTheMaxPageChangesGiven(): void
    {
        // home goes to itself, endlessly
        $routes = new RouterBuilder('home');
        $routes->addRoute(
            'home',
            new CallbackController(NoParameters::class, static fn(Request $request) => new ChangePage('home')),
        );
        $logger = new MemoryLogger();

        self::serve(
            KernelBuilder::for($routes->build(), (new MessagesXmlParser())->defaults())
                ->withLogger($logger)
                ->withContextKey('secret')
                ->withMaxPageChanges(2),
            query: 'web=true',
        );

        self::assertContains(
            [
                'Error serving the request',
                'Too many page changes in one request (more than 2): home → home → home → home',
            ],
            array_map(static fn(array $record): array => [
                $record[1],
                ($record[2]['exception'] ?? null) instanceof \Throwable ? $record[2]['exception']->getMessage() : null,
            ], $logger->records),
        );
    }

    #[RunInSeparateProcess]
    public function testTheHtmlClientEmbedsTheFontGiven(): void
    {
        // Embedded as it is: any bytes do
        $font = tempnam(sys_get_temp_dir(), prefix: 'font');
        if ($font === false) {
            self::fail('No temporary file');
        }
        file_put_contents($font, data: 'not the Minitel font');

        try {
            $html = self::serve($this->builder->withContextKey('secret')->withFont($font), query: 'web=true');
        } finally {
            unlink($font);
        }

        self::assertStringContainsString(base64_encode('not the Minitel font'), $html);
    }

    public function testAFontThatCantBeReadIsRefusedWhenGiven(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('Font file not found: /missing.ttf');

        $this->builder->withFont('/missing.ttf');
    }

    public function testAFolderIsRefusedAsFont(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('Font file not found: ' . __DIR__);

        $this->builder->withFont(__DIR__);
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
