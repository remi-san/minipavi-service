<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Core;

use MiniPaviService\Controller\ControllerState;
use MiniPaviService\Controller\Pagination\PageParameters;
use MiniPaviService\Controller\Parameters\NoParameters;
use MiniPaviService\Controller\Parameters\Parameters;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;
use MiniPaviService\Controller\Response\ChangePage;
use MiniPaviService\Controller\Response\DisplayPage;
use MiniPaviService\Controller\Response\RunCommand;
use MiniPaviService\Core\ContextSerializer;
use MiniPaviService\Core\RouterBuilder;
use MiniPaviService\Core\Service;
use MiniPaviService\Core\Sessions;
use MiniPaviService\Core\Signing\ForgedContextException;
use MiniPaviService\Core\Signing\HmacContextSigner;
use MiniPaviService\Gateway\Command\CallMode;
use MiniPaviService\Gateway\Command\InputCommand;
use MiniPaviService\Gateway\Command\ServiceCallCommand;
use MiniPaviService\Gateway\Command\WaitForKeyCommand;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\Message\FrameworkMessage;
use MiniPaviService\Message\Messages;
use MiniPaviService\Message\MessagesException;
use MiniPaviService\Message\MessagesXmlParser;
use MiniPaviService\Minitel\ConnectionCode;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\InputField;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\Span;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Tests\Support\CallbackController;
use MiniPaviService\Tests\Support\ClearAndShow;
use MiniPaviService\Tests\Support\FailingSigner;
use MiniPaviService\Tests\Support\MemoryLogger;
use MiniPaviService\Vdt\Screen\Screen;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

/**
 * The service as the gateway sees it (Service::handle()): each response's context is given back
 * with the next request, as the gateway does, and what is checked is what the user sees and
 * what the controllers run on
 */
final class ServiceTest extends TestCase
{
    private const TERMINAL_ADDRESS = '192.0.2.1';

    /** @var array<string, list<Request<ControllerState, Parameters>>> requests each controller ran on, by route */
    private array $requests = [];

    private RouterBuilder $routes;

    private MemoryLogger $logger;

    private Terminal $terminal;

    // Page changes the service allows in one request
    private int $maxPageChanges = 10;

    protected function setUp(): void
    {
        $this->logger = new MemoryLogger();
        $this->terminal = new Terminal(self::TERMINAL_ADDRESS, 'websocket', 'Cv5');
        $this->routes = $this->routesStartingAt('menu');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function contextsOfADisconnection(): iterable
    {
        yield 'none' => [''];
        yield 'invalid' => ['not a context'];
        yield 'valid' => ['{"route":"directory","states":{},"parameters":{}}'];
    }

    #[DataProvider('contextsOfADisconnection')]
    public function testDisconnectionDoesNotReadTheContext(string $context): void
    {
        self::assertNull($this->handle(new GatewayRequest(ConnectionCode::Disconnect, [], $context)));
        self::assertSame([], $this->requests);
        // Nothing went wrong
        self::assertSame([[LogLevel::INFO, 'Disconnection', []]], $this->logger->records);
    }

    #[DataProvider('contextsOfADisconnection')]
    public function testBackgroundCallGetsNoResponseNorReadsTheContext(string $context): void
    {
        self::assertNull($this->handle(new GatewayRequest(ConnectionCode::BackgroundCall, [], $context)));
        self::assertSame([], $this->requests);
        self::assertSame([LogLevel::INFO], $this->levels());
    }

    public function testBackgroundCallIsLoggedWithItsCode(): void
    {
        $this->handle(new GatewayRequest(ConnectionCode::BackgroundCall));

        self::assertSame(
            [[LogLevel::INFO, 'Background call, not responded to', ['fctn' => 'BGCALL-WM-']]],
            $this->logger->records,
        );
    }

    public function testAnErrorThatCantBeRespondedToIsCritical(): void
    {
        // No context can be signed: not even the error response's
        $router = $this->routes->build();
        $service = new Service(
            new Sessions(new ContextSerializer($router, new FailingSigner()), $router),
            (new MessagesXmlParser())->defaults(),
            logger: $this->logger,
        );

        self::assertNull($service->handle(new GatewayRequest(ConnectionCode::Connect)));
        self::assertSame([LogLevel::INFO, LogLevel::ERROR, LogLevel::CRITICAL], $this->levels());
        [, $message, $context] = $this->logger->records[2];
        self::assertSame('Cannot respond to the error', $message);
        $exception = $context['exception'] ?? null;
        self::assertInstanceOf(\RuntimeException::class, $exception);
        self::assertSame('Cannot sign', $exception->getMessage());
    }

    public function testContextsOfConnectionsAndBackgroundCallsArentChecked(): void
    {
        // Their context isn't read: a service signing its contexts doesn't refuse it
        $router = $this->routes->build();
        $service = new Service(
            new Sessions(new ContextSerializer($router, new HmacContextSigner('secret')), $router),
            (new MessagesXmlParser())->defaults(),
            logger: $this->logger,
        );

        self::assertSame(
            'menu',
            $this->contentOf($service->handle(new GatewayRequest(ConnectionCode::Connect, [], 'forged'))),
        );
        self::assertNull($service->handle(new GatewayRequest(ConnectionCode::BackgroundCall, [], 'forged')));
        self::assertSame([LogLevel::INFO, LogLevel::INFO], $this->levels());
    }

    public function testForgedContextIsAnError(): void
    {
        $router = $this->routes->build();
        $service = new Service(
            new Sessions(new ContextSerializer($router, new HmacContextSigner('secret')), $router),
            (new MessagesXmlParser())->defaults(),
            logger: $this->logger,
        );

        $response = $service->handle(
            new GatewayRequest(FunctionKey::Send, [], '{"route":"directory","states":{},"parameters":{}}'),
        );

        self::assertStringContainsString(self::errorMessage(), self::line0($response));
        self::assertInstanceOf(ForgedContextException::class, $this->error());
    }

    /**
     * @return iterable<string, array{ConnectionCode}>
     */
    public static function connections(): iterable
    {
        yield 'new connection' => [ConnectionCode::Connect];
        yield 'connection from another service' => [ConnectionCode::DirectConnect];
    }

    #[DataProvider('connections')]
    public function testConnectionStartsAtTheDefaultRoute(ConnectionCode $code): void
    {
        self::assertSame(
            'menu',
            $this->contentOf($this->handle(new GatewayRequest($code, ['typed before'], 'not a context'))),
        );
        // A new session: at init, and without what was typed before
        self::assertSame(ControllerState::Init, $this->lastRequest('menu')->state());
        self::assertSame([$code, []], [$this->lastRequest('menu')->key(), $this->lastRequest('menu')->content()]);
        self::assertSame([[LogLevel::INFO, 'Connection', ['fctn' => $code->value]]], $this->logger->records);
    }

    public function testAKeyTheFrameworkDoesntKnowContinuesTheSession(): void
    {
        $directory = $this->press($this->connect(), key: FunctionKey::Send);

        // E.g. DIRECT, after a CallMode::Direct call (GatewayRequest::keyOf())
        self::assertSame(
            'code 36',
            $this->contentOf($this->handle(new GatewayRequest(null, ['36'], $directory->context, $this->terminal))),
        );
        self::assertSame([null, ['36']], [
            $this->lastRequest('directory')->key(),
            $this->lastRequest('directory')->content(),
        ]);
    }

    public function testKeyRunsTheControllerTheUserIsAt(): void
    {
        $directory = $this->press($this->connect(), key: FunctionKey::Send);

        self::assertSame(
            'code 3615',
            $this->contentOf($this->press($directory, key: FunctionKey::Send, content: ['36', '15'])),
        );
    }

    public function testErrorIsLoggedAndRespondedToOnLine0(): void
    {
        $response = $this->goTo('broken');

        self::assertStringContainsString(self::errorMessage(), self::line0($response));
        self::assertEquals(new WaitForKeyCommand(), $response->command);
        $error = $this->error();
        self::assertStringContainsString('Template data missing', $error->getMessage());
        self::assertInstanceOf(\RuntimeException::class, $error);
    }

    public function testNextKeyAfterAnErrorStartsAgainAtTheDefaultRoute(): void
    {
        // The menu kept its state before the error
        $error = $this->goTo('broken');

        self::assertSame('menu', $this->contentOf($this->press($error, key: FunctionKey::Summary)));
        self::assertSame(ControllerState::Init, $this->lastRequest('menu')->state());
        self::assertSame(FunctionKey::Summary, $this->lastRequest('menu')->key());
    }

    public function testInvalidContextIsAnError(): void
    {
        self::assertStringContainsString(
            self::errorMessage(),
            self::line0($this->handle(new GatewayRequest(FunctionKey::Send, [], 'not a context'))),
        );
        self::assertSame([LogLevel::ERROR], $this->levels());
    }

    public function testErrorOfTheDefaultRouteIsRespondedToOnce(): void
    {
        // On a connection, the default controller fails: no loop, an error response
        $this->routes = $this->routesStartingAt('broken');

        self::assertStringContainsString(self::errorMessage(), self::line0($this->connect()));
        self::assertCount(1, $this->requests['broken']);
    }

    public function testErrorMessageCanBeChanged(): void
    {
        $router = $this->routes->build();
        $service = new Service(
            new Sessions(new ContextSerializer($router), $router),
            (new MessagesXmlParser())
                ->defaults()
                ->with(['error' => 'Oops']),
            logger: $this->logger,
        );

        self::assertStringContainsString(
            'Oops',
            self::line0($service->handle(new GatewayRequest(FunctionKey::Send, [], 'not a context'))),
        );
    }

    public function testMessagesMustHaveTheFrameworks(): void
    {
        $this->expectException(MessagesException::class);
        $this->expectExceptionMessage('No text for the messages: error, page.first, page.last, endkey, endkey.shift');

        $router = $this->routes->build();
        new Service(new Sessions(new ContextSerializer($router), $router), new Messages(['other' => 'x']));
    }

    public function testEndlessPageChangesAreStoppedAndRespondedToWithAnError(): void
    {
        $menu = $this->connect();
        $this->requests = [];

        $response = $this->press($menu, key: FunctionKey::Next, content: ['ping']);

        self::assertStringContainsString(self::errorMessage(), self::line0($response));
        // The menu, then the page changes allowed (10 by default), and no more
        self::assertCount(10 + 1, array_merge(...array_values($this->requests)));
        self::assertStringContainsString(
            'Too many page changes in one request (more than 10): menu → ping → pong → ping',
            $this->error()->getMessage(),
        );
    }

    public function testThePageChangesAllowedCanBeGiven(): void
    {
        $this->maxPageChanges = 2;
        $menu = $this->connect();
        $this->requests = [];

        $response = $this->press($menu, key: FunctionKey::Next, content: ['ping']);

        self::assertStringContainsString(self::errorMessage(), self::line0($response));
        self::assertCount(2 + 1, array_merge(...array_values($this->requests)));
        self::assertStringContainsString(
            'Too many page changes in one request (more than 2): menu → ping → pong → ping',
            $this->error()->getMessage(),
        );
    }

    public function testNoPageChangeCanBeAllowed(): void
    {
        $this->maxPageChanges = 0;
        $menu = $this->connect();
        $this->requests = [];

        $response = $this->press($menu, key: FunctionKey::Next, content: ['ping']);

        self::assertStringContainsString(self::errorMessage(), self::line0($response));
        self::assertCount(1, array_merge(...array_values($this->requests)));
        self::assertStringContainsString(
            'Too many page changes in one request (more than 0): menu → ping',
            $this->error()->getMessage(),
        );
    }

    public function testChangePageStartsTheTargetAtInitWithItsParametersWithoutTheInput(): void
    {
        $this->press($this->connect(), key: FunctionKey::Send, content: ['1']);

        // The directory must not read the menu's "1" as a code
        $directory = $this->lastRequest('directory');
        self::assertSame(ControllerState::Init, $directory->state());
        self::assertEquals(new PageParameters(1), $directory->parameters());
        self::assertNull($directory->key());
        self::assertSame([], $directory->content());
        self::assertSame(self::TERMINAL_ADDRESS, $directory->terminal()->remoteAddr());
    }

    public function testChangePageResetsTheControllerLeft(): void
    {
        // The menu reads the next key, then is left for the directory, which goes back to it
        $directory = $this->press($this->connect(), key: FunctionKey::Send);
        $this->press($directory, key: FunctionKey::Summary);

        self::assertSame(ControllerState::Init, $this->lastRequest('menu')->state());
    }

    public function testUnknownRouteStartsAgainAtTheDefaultRoute(): void
    {
        // A context of a previous version of the service, at a route removed since
        $routes = new RouterBuilder('removed');
        $routes->addRoute('removed', $this->controller(
            'removed',
            NoParameters::class,
            static fn(Request $request) => new DisplayPage(new Vdt('removed')),
        ));
        $previous = $routes->build();
        $context =
            (new Service(
                new Sessions(new ContextSerializer($previous), $previous),
                (new MessagesXmlParser())->defaults(),
            ))->handle(new GatewayRequest(ConnectionCode::Connect, terminal: $this->terminal))->context ?? '';

        $response = $this->handle(new GatewayRequest(FunctionKey::Send, ['1'], $context, $this->terminal));

        self::assertSame('menu', $this->contentOf($response));
        // As a new connection: the input was for a screen that doesn't exist any more
        $menu = $this->lastRequest('menu');
        self::assertSame(ControllerState::Init, $menu->state());
        self::assertSame([null, []], [$menu->key(), $menu->content()]);
        self::assertSame(
            [[
                LogLevel::WARNING,
                'Unknown route in the context, starting again at the default route',
                ['route' => 'removed'],
            ]],
            $this->logger->records,
        );
    }

    public function testDisplayRespondsWithTheContentAndKeepsItsStateAndParameters(): void
    {
        $response = $this->responseTo(
            new DisplayPage(new Vdt('Hello'), ControllerState::Display, new PageParameters(2)),
        );

        self::assertSame('Hello', $response->vdt->toString());
        // To this service, waiting for the user
        self::assertEquals(new WaitForKeyCommand(), $response->command);
        $next = $this->nextRequest($response);
        self::assertSame(ControllerState::Display, $next->state());
        self::assertEquals(new PageParameters(2), $next->parameters());
    }

    public function testDisplayReadsTheNextKeyWithTheDefaultParameters(): void
    {
        $next = $this->nextRequest($this->responseTo(new DisplayPage(new Vdt('Hello'))));

        self::assertSame(ControllerState::ReadInput, $next->state());
        self::assertEquals(new PageParameters(), $next->parameters());
    }

    public function testDisplayCanKeepTheInitialState(): void
    {
        $next = $this->nextRequest($this->responseTo(new DisplayPage(new Vdt('Hello'), state: null)));

        self::assertSame(ControllerState::Init, $next->state());
    }

    public function testReadInputRespondsWithTheCommand(): void
    {
        $command = new InputCommand(new InputField(new Span(new Position(2, 1), 4), validWith: [FunctionKey::Send]));

        $response = $this->responseTo(RunCommand::readInput(new Vdt('Code ?'), $command));

        self::assertSame('Code ?', $response->vdt->toString());
        self::assertSame($command, $response->command);
        // What is typed comes back to the controller, reading it
        $next = $this->nextRequest($response);
        self::assertSame(ControllerState::ReadInput, $next->state());
        self::assertEquals(new PageParameters(), $next->parameters());
    }

    public function testReadInputKeepsItsParameters(): void
    {
        $next = $this->nextRequest($this->responseTo(RunCommand::readInput(
            new Vdt('Code ?'),
            command: null,
            parameters: new PageParameters(2),
        )));

        self::assertSame(ControllerState::ReadInput, $next->state());
        self::assertEquals(new PageParameters(2), $next->parameters());
    }

    /**
     * @return iterable<string, array{Response<ControllerState>}>
     */
    public static function inputsRead(): iterable
    {
        $command = new InputCommand(new InputField(new Span(new Position(2, 1), 4), validWith: [FunctionKey::Send]));

        yield 'readInput()' => [RunCommand::readInput(new Vdt('Code ?'), $command)];
        // Given its state, as it is built directly
        yield 'a command built directly' => [new RunCommand(new Vdt('Code ?'), $command, ControllerState::ReadInput)];
    }

    /**
     * @param Response<ControllerState> $response
     */
    #[DataProvider('inputsRead')]
    public function testWhatIsTypedReachesTheControllerReadingIt(Response $response): void
    {
        $this->press($this->responseTo($response), key: FunctionKey::Send, content: ['3615']);

        // Not dropped: the controller reads it, rather than starting again (onInit())
        $next = $this->lastRequest('respond');
        self::assertSame(ControllerState::ReadInput, $next->state());
        self::assertSame([FunctionKey::Send, ['3615']], [$next->key(), $next->content()]);
    }

    public function testReadInputWithoutCommandOnlySendsTheContent(): void
    {
        // Page::inputCommand() gives null for a page without <input>
        $response = $this->responseTo(RunCommand::readInput(new Vdt('Hello'), command: null));

        self::assertSame('Hello', $response->vdt->toString());
        self::assertEquals(new WaitForKeyCommand(), $response->command);
    }

    /**
     * @return iterable<string, array{?ControllerState, ?PageParameters, ControllerState, PageParameters}>
     */
    public static function keptForTheReturn(): iterable
    {
        // Given, then as the next request gets them
        yield 'nothing' => [null, null, ControllerState::Init, new PageParameters()];
        yield 'parameters' => [null, new PageParameters(4), ControllerState::Init, new PageParameters(4)];
        yield 'a state and parameters' => [
            ControllerState::Display,
            new PageParameters(4),
            ControllerState::Display,
            new PageParameters(4),
        ];
    }

    #[DataProvider('keptForTheReturn')]
    public function testConnectToSendsTheUserToTheOtherServiceAndKeepsWhatItIsGiven(
        ?ControllerState $state,
        ?PageParameters $parameters,
        ControllerState $nextState,
        PageParameters $nextParameters,
    ): void {
        $response = $this->responseTo(RunCommand::connectTo(
            'https://example.org/service',
            new Vdt('Cnx en cours'),
            $state,
            $parameters,
        ));

        self::assertSame('Cnx en cours', $response->vdt->toString());
        self::assertEquals(
            new ServiceCallCommand('https://example.org/service', CallMode::Connect),
            $response->command,
        );
        // For when the user comes back
        $next = $this->nextRequest($response);
        self::assertSame($nextState, $next->state());
        self::assertEquals($nextParameters, $next->parameters());
    }

    public function testRunCommandSendsAnyCommand(): void
    {
        // A direct call, as the continuation of the session: neither readInput() nor connectTo()
        // gives one
        $command = new ServiceCallCommand('https://example.org/', CallMode::Direct);

        $response = $this->responseTo(new RunCommand(new Vdt(), $command, null));

        // Without content: none sent
        self::assertSame('', $response->vdt->toString());
        self::assertSame($command, $response->command);
        self::assertSame(ControllerState::Init, $this->nextRequest($response)->state());
    }

    public function testAResponseOfItsOwnNeedsNoChangeToTheService(): void
    {
        $response = $this->responseTo(new ClearAndShow('Hello'));

        self::assertStringStartsWith('Hello', Screen::of($response->vdt)->line(1));
        // Its state and parameters are kept, as for any response
        $next = $this->nextRequest($response);
        self::assertSame(ControllerState::Display, $next->state());
        self::assertEquals(new PageParameters(1), $next->parameters());
    }

    public function testKeepingParametersOfAnotherClassIsAnError(): void
    {
        $response = $this->press($this->connect(), key: FunctionKey::Next, content: ['mistyped']);

        self::assertStringContainsString(self::errorMessage(), self::line0($response));
        self::assertStringContainsString(
            'keeps parameters of MiniPaviService\\Controller\\Parameters\\NoParameters, not MiniPaviService\\Controller\\Pagination\\PageParameters',
            $this->error()->getMessage(),
        );
    }

    /**
     * The routes of the tests, new connections starting at defaultRoute
     */
    private function routesStartingAt(string $defaultRoute): RouterBuilder
    {
        // menu: Envoi goes to the directory, at page 1, Suite to the route typed;
        // directory: shows its page, reads a code, Sommaire goes back to the menu
        $routes = new RouterBuilder($defaultRoute);
        $routes->addRoute('menu', $this->controller(
            'menu',
            NoParameters::class,
            static fn(Request $request) => match ($request->functionKey()) {
                FunctionKey::Send => new ChangePage('directory', new PageParameters(1)),
                FunctionKey::Next => new ChangePage($request->contentAsString()),
                default => new DisplayPage(new Vdt('menu')),
            },
        ));
        $routes->addRoute('directory', $this->controller(
            'directory',
            PageParameters::class,
            static fn(Request $request) => match (true) {
                $request->functionKey() === FunctionKey::Summary => new ChangePage('menu'),
                $request->state() === ControllerState::ReadInput => new DisplayPage(Vdt::text(
                    'code ' . $request->contentAsString(),
                )),
                default => new DisplayPage(new Vdt('directory'), parameters: new PageParameters(2)),
            },
        ));
        $routes->addRoute('broken', $this->controller(
            'broken',
            NoParameters::class,
            static fn(Request $request) => throw new \RuntimeException('Template data missing'),
        ));
        // Two controllers changing to each other
        $routes->addRoute('ping', $this->controller(
            'ping',
            NoParameters::class,
            static fn(Request $request) => new ChangePage('pong'),
        ));
        $routes->addRoute('pong', $this->controller(
            'pong',
            NoParameters::class,
            static fn(Request $request) => new ChangePage('ping'),
        ));
        // Keeps parameters of another class than it declares
        $routes->addRoute('mistyped', $this->controller(
            'mistyped',
            NoParameters::class,
            static fn(Request $request) => new DisplayPage(new Vdt('x'), parameters: new PageParameters(1)),
        ));

        return $routes;
    }

    /**
     * Controller recording the requests it runs on, in $this->requests
     *
     * @template TParameters of Parameters
     *
     * @param class-string<TParameters> $parametersType
     * @param \Closure(Request<ControllerState, TParameters>): Response<ControllerState> $run
     *
     * @return CallbackController<TParameters>
     */
    private function controller(string $route, string $parametersType, \Closure $run): CallbackController
    {
        return new CallbackController(
            $parametersType,
            /**
             * @param Request<ControllerState, TParameters> $request
             *
             * @return Response<ControllerState>
             */
            function (Request $request) use ($route, $run): Response {
                $this->requests[$route][] = $request;

                return $run($request);
            },
        );
    }

    /**
     * Response of the service on a connection, when the controller of its default route returns
     * response
     *
     * @param Response<ControllerState> $response
     */
    private function responseTo(Response $response): GatewayResponse
    {
        $this->routes = $this->routesStartingAt('respond');
        $this->routes->addRoute('respond', $this->controller(
            'respond',
            PageParameters::class,
            static fn(Request $request) => $response,
        ));

        return $this->connect();
    }

    /**
     * Request the controller of the response runs on, when the user presses a key after it
     */
    private function nextRequest(GatewayResponse $response): Request
    {
        $this->press($response, key: FunctionKey::Send);

        return $this->lastRequest('respond');
    }

    /**
     * Response of the service to the request of the gateway
     */
    private function handle(GatewayRequest $request): ?GatewayResponse
    {
        $router = $this->routes->build();

        return (new Service(
            new Sessions(new ContextSerializer($router), $router, $this->maxPageChanges),
            (new MessagesXmlParser())->defaults(),
            $this->logger,
        ))->handle($request);
    }

    private function connect(): GatewayResponse
    {
        return (
            $this->handle(new GatewayRequest(ConnectionCode::Connect, terminal: $this->terminal)) ?? self::fail(
                'No response',
            )
        );
    }

    /**
     * Response to a key pressed after the previous response, with its context, as the gateway
     * sends it
     *
     * @param list<string> $content
     */
    private function press(GatewayResponse $previous, FunctionKey $key, array $content = []): GatewayResponse
    {
        return (
            $this->handle(new GatewayRequest($key, $content, $previous->context, $this->terminal)) ?? self::fail(
                'No response',
            )
        );
    }

    /**
     * Response when the menu goes to route
     */
    private function goTo(string $route): GatewayResponse
    {
        return $this->press($this->connect(), key: FunctionKey::Next, content: [$route]);
    }

    /**
     * @return Request<ControllerState, Parameters>
     */
    private function lastRequest(string $route): Request
    {
        $requests = $this->requests[$route] ?? [];

        return $requests[\count($requests) - 1] ?? self::fail("{$route} didn't run");
    }

    /**
     * Failure logged as an error: always with the same message, the failure in its context
     */
    private function error(): \Throwable
    {
        foreach ($this->logger->records as [$level, $message, $context]) {
            if ($level !== LogLevel::ERROR) {
                continue;
            }
            self::assertSame('Error serving the request', $message);
            $exception = $context['exception'] ?? null;
            self::assertInstanceOf(\Throwable::class, $exception);

            return $exception;
        }

        self::fail('No error logged');
    }

    /**
     * Levels of what was logged, in order
     *
     * @return list<mixed>
     */
    private function levels(): array
    {
        return array_column($this->logger->records, 0);
    }

    private static function errorMessage(): string
    {
        return (new MessagesXmlParser())->defaults()->get(FrameworkMessage::Error);
    }

    /**
     * Status line the response shows
     */
    private static function line0(?GatewayResponse $response): string
    {
        return Screen::of($response->vdt ?? self::fail('No response'))->line(0);
    }

    /**
     * Videotex content of a response
     */
    private function contentOf(?GatewayResponse $response): string
    {
        return $response?->vdt->toString() ?? self::fail('No response');
    }
}
