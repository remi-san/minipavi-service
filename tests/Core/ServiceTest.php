<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Core;

use MiniPaviService\Controller\ControllerState;
use MiniPaviService\Controller\Pagination\PageParameters;
use MiniPaviService\Controller\Parameters\NoParameters;
use MiniPaviService\Controller\Parameters\Parameters;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;
use MiniPaviService\Core\ContextSerializer;
use MiniPaviService\Core\RouterBuilder;
use MiniPaviService\Core\Service;
use MiniPaviService\Core\Signing\ForgedContextException;
use MiniPaviService\Core\Signing\HmacContextSigner;
use MiniPaviService\Gateway\CallMode;
use MiniPaviService\Gateway\ConnectionCode;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\Gateway\InputCommand;
use MiniPaviService\Message\FrameworkMessage;
use MiniPaviService\Message\Messages;
use MiniPaviService\Message\MessagesException;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\InputField;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Tests\Support\CallbackController;
use MiniPaviService\Tests\Support\ClearAndShow;
use MiniPaviService\Tests\Support\MemoryLogger;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

/**
 * The service as the gateway sees it (Service::handle()): each answer's context is given back
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
        self::assertSame([LogLevel::INFO], $this->levels());
    }

    #[DataProvider('contextsOfADisconnection')]
    public function testBackgroundCallIsNotAnsweredNorReadsTheContext(string $context): void
    {
        self::assertNull($this->handle(new GatewayRequest(ConnectionCode::BackgroundCall, [], $context)));
        self::assertSame([], $this->requests);
        self::assertSame([LogLevel::INFO], $this->levels());
    }

    public function testContextsOfConnectionsAndBackgroundCallsArentChecked(): void
    {
        // Their context isn't read: a service signing its contexts doesn't refuse it
        $router = $this->routes->build();
        $service = new Service(
            $router,
            Messages::defaults(),
            new ContextSerializer($router, new HmacContextSigner('secret')),
            $this->logger,
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
            $router,
            Messages::defaults(),
            new ContextSerializer($router, new HmacContextSigner('secret')),
            $this->logger,
        );

        $answer = $service->handle(
            new GatewayRequest(FunctionKey::Send, [], '{"route":"directory","states":{},"parameters":{}}'),
        );

        self::assertStringContainsString(self::errorMessage(), self::line0($answer));
        self::assertInstanceOf(ForgedContextException::class, $this->error()[1]['exception'] ?? null);
    }

    public function testConnectionStartsAtTheDefaultRoute(): void
    {
        self::assertSame(
            'menu',
            $this->contentOf($this->handle(
                new GatewayRequest(ConnectionCode::Connect, ['typed before'], 'not a context'),
            )),
        );
        // A new session: at init, and without what was typed before
        self::assertSame(ControllerState::Init, $this->lastRequest('menu')->state());
        self::assertSame([], $this->lastRequest('menu')->content());
        self::assertSame([LogLevel::INFO], $this->levels());
        self::assertSame(['fctn' => 'CNX'], $this->logger->records[0][2]);
    }

    public function testKeyRunsTheControllerTheUserIsAt(): void
    {
        $directory = $this->press($this->connect(), key: FunctionKey::Send);

        self::assertSame(
            'code 3615',
            $this->contentOf($this->press($directory, key: FunctionKey::Send, content: ['36', '15'])),
        );
    }

    public function testErrorIsLoggedAndAnsweredOnLine0(): void
    {
        $answer = $this->goTo('broken');

        self::assertStringContainsString(self::errorMessage(), self::line0($answer));
        self::assertNull($answer->command);
        [$message, $context] = $this->error();
        self::assertStringContainsString('Template data missing', $message);
        self::assertInstanceOf(\RuntimeException::class, $context['exception'] ?? null);
    }

    public function testNextKeyAfterAnErrorStartsAgainAtTheDefaultRoute(): void
    {
        // The menu kept its state before the error
        $error = $this->goTo('broken');

        self::assertSame('menu', $this->contentOf($this->press($error, key: FunctionKey::Summary)));
        self::assertSame(ControllerState::Init, $this->lastRequest('menu')->state());
        self::assertSame('SOMMAIRE', $this->lastRequest('menu')->key());
    }

    public function testInvalidContextIsAnError(): void
    {
        self::assertStringContainsString(
            self::errorMessage(),
            self::line0($this->handle(new GatewayRequest(FunctionKey::Send, [], 'not a context'))),
        );
        self::assertSame([LogLevel::ERROR], $this->levels());
    }

    public function testErrorOfTheDefaultRouteIsAnsweredOnce(): void
    {
        // On a connection, the default controller fails: no loop, an error answer
        $this->routes = $this->routesStartingAt('broken');

        self::assertStringContainsString(self::errorMessage(), self::line0($this->connect()));
        self::assertCount(1, $this->requests['broken']);
    }

    public function testErrorMessageCanBeChanged(): void
    {
        $router = $this->routes->build();
        $service = new Service(
            $router,
            Messages::defaults()->with(['error' => 'Oops']),
            new ContextSerializer($router),
            $this->logger,
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
        new Service($router, new Messages(['other' => 'x']), new ContextSerializer($router));
    }

    public function testEndlessPageChangesAreStoppedAndAnsweredAsAnError(): void
    {
        $menu = $this->connect();
        $this->requests = [];

        $answer = $this->press($menu, key: FunctionKey::Next, content: ['ping']);

        self::assertStringContainsString(self::errorMessage(), self::line0($answer));
        // The menu, then the page changes allowed, and no more
        self::assertCount(Service::MAX_PAGE_CHANGES + 1, array_merge(...array_values($this->requests)));
        self::assertStringContainsString(
            'Too many page changes in one request (more than 10): menu → ping → pong → ping',
            $this->error()[0],
        );
    }

    public function testChangePageStartsTheTargetAtInitWithItsParametersWithoutTheInput(): void
    {
        $this->press($this->connect(), key: FunctionKey::Send, content: ['1']);

        // The directory must not read the menu's "1" as a code
        $directory = $this->lastRequest('directory');
        self::assertSame(ControllerState::Init, $directory->state());
        self::assertEquals(new PageParameters(1), $directory->parameters());
        self::assertSame('', $directory->key());
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
            static fn(Request $request) => Response::display(new Vdt('removed')),
        ));
        $previous = $routes->build();
        $context =
            (new Service($previous, Messages::defaults(), new ContextSerializer($previous)))->handle(
                new GatewayRequest(ConnectionCode::Connect, terminal: $this->terminal),
            )->context ?? '';

        $answer = $this->handle(new GatewayRequest(FunctionKey::Send, ['1'], $context, $this->terminal));

        self::assertSame('menu', $this->contentOf($answer));
        // As a new connection: the input was for a screen that doesn't exist any more
        $menu = $this->lastRequest('menu');
        self::assertSame(ControllerState::Init, $menu->state());
        self::assertSame(['', []], [$menu->key(), $menu->content()]);
        self::assertSame([LogLevel::WARNING], $this->levels());
        self::assertSame(['route' => 'removed'], $this->logger->records[0][2]);
    }

    public function testDisplayAnswersTheContentAndKeepsItsStateAndParameters(): void
    {
        $answer = $this->answerTo(Response::display(new Vdt('Hello'), ControllerState::Display, new PageParameters(2)));

        self::assertSame('Hello', $answer->vdt->toString());
        // To this service, waiting for the user
        self::assertNull($answer->next);
        self::assertNull($answer->directCall);
        self::assertNull($answer->command);
        $next = $this->nextRequest($answer);
        self::assertSame(ControllerState::Display, $next->state());
        self::assertEquals(new PageParameters(2), $next->parameters());
    }

    public function testReadInputAnswersWithTheCommand(): void
    {
        $command = new InputCommand(new InputField(row: 2, col: 1, length: 4, validWith: [FunctionKey::Send]));

        $answer = $this->answerTo(Response::readInput(new Vdt('Code ?'), $command));

        self::assertSame('Code ?', $answer->vdt->toString());
        self::assertSame($command, $answer->command);
        self::assertNull($answer->next);
        self::assertNull($answer->directCall);
        // What is typed comes back to the controller, reading it
        $next = $this->nextRequest($answer);
        self::assertSame(ControllerState::ReadInput, $next->state());
        self::assertEquals(new PageParameters(), $next->parameters());
    }

    public function testReadInputWithoutCommandOnlySendsTheContent(): void
    {
        // Page::inputCommand() gives null for a page without <input>
        $answer = $this->answerTo(Response::readInput(new Vdt('Hello'), command: null));

        self::assertSame('Hello', $answer->vdt->toString());
        self::assertNull($answer->command);
    }

    public function testConnectToSendsTheUserToTheOtherServiceAndKeepsWhatItIsGiven(): void
    {
        $answer = $this->answerTo(Response::connectTo(
            'https://example.org/service',
            new Vdt('Cnx en cours'),
            parameters: new PageParameters(4),
        ));

        self::assertSame('Cnx en cours', $answer->vdt->toString());
        self::assertSame('https://example.org/service', $answer->next);
        self::assertSame(CallMode::Connect, $answer->directCall);
        // For when the user comes back
        $next = $this->nextRequest($answer);
        self::assertSame(ControllerState::Init, $next->state());
        self::assertEquals(new PageParameters(4), $next->parameters());
    }

    public function testAResponseOfItsOwnNeedsNoChangeToTheService(): void
    {
        $answer = $this->answerTo(new ClearAndShow('Hello'));

        self::assertStringStartsWith('Hello', $answer->vdt->screen()->line(1));
        // Its state and parameters are kept, as for any response
        $next = $this->nextRequest($answer);
        self::assertSame(ControllerState::Display, $next->state());
        self::assertEquals(new PageParameters(1), $next->parameters());
    }

    public function testKeepingParametersOfAnotherClassIsAnError(): void
    {
        $answer = $this->press($this->connect(), key: FunctionKey::Next, content: ['mistyped']);

        self::assertStringContainsString(self::errorMessage(), self::line0($answer));
        self::assertStringContainsString(
            'keeps parameters of MiniPaviService\\Controller\\Parameters\\NoParameters, not MiniPaviService\\Controller\\Pagination\\PageParameters',
            $this->error()[0],
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
            static fn(Request $request) => match ($request->key()) {
                'ENVOI' => Response::goTo('directory', new PageParameters(1)),
                'SUITE' => Response::goTo($request->contentAsString()),
                default => Response::display(new Vdt('menu')),
            },
        ));
        $routes->addRoute('directory', $this->controller(
            'directory',
            PageParameters::class,
            static fn(Request $request) => match (true) {
                $request->key() === 'SOMMAIRE' => Response::goTo('menu'),
                $request->state() === ControllerState::ReadInput => Response::display(Vdt::text(
                    'code ' . $request->contentAsString(),
                )),
                default => Response::display(new Vdt('directory'), parameters: new PageParameters(2)),
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
            static fn(Request $request) => Response::goTo('pong'),
        ));
        $routes->addRoute('pong', $this->controller(
            'pong',
            NoParameters::class,
            static fn(Request $request) => Response::goTo('ping'),
        ));
        // Keeps parameters of another class than it declares
        $routes->addRoute('mistyped', $this->controller(
            'mistyped',
            NoParameters::class,
            static fn(Request $request) => Response::display(new Vdt('x'), parameters: new PageParameters(1)),
        ));

        return $routes;
    }

    /**
     * Controller recording the requests it runs on, in $this->requests
     *
     * @template TParameters of Parameters
     *
     * @param class-string<TParameters> $parametersType
     * @param \Closure(Request<ControllerState, TParameters>): Response $run
     *
     * @return CallbackController<TParameters>
     */
    private function controller(string $route, string $parametersType, \Closure $run): CallbackController
    {
        return new CallbackController(
            $parametersType,
            /** @param Request<ControllerState, TParameters> $request */
            function (Request $request) use ($route, $run): Response {
                $this->requests[$route][] = $request;

                return $run($request);
            },
        );
    }

    /**
     * Answer of the service on a connection, when the controller of its default route returns
     * response
     */
    private function answerTo(Response $response): GatewayResponse
    {
        $this->routes = $this->routesStartingAt('answer');
        $this->routes->addRoute('answer', $this->controller(
            'answer',
            PageParameters::class,
            static fn(Request $request) => $response,
        ));

        return $this->connect();
    }

    /**
     * Request the controller of the answer runs on, when the user presses a key after it
     */
    private function nextRequest(GatewayResponse $answer): Request
    {
        $this->press($answer, key: FunctionKey::Send);

        return $this->lastRequest('answer');
    }

    /**
     * Answer of the service to the request of the gateway
     *
     * @param GatewayRequest<\MiniPaviService\Gateway\ClientState> $request
     */
    private function handle(GatewayRequest $request): ?GatewayResponse
    {
        $router = $this->routes->build();

        return (new Service($router, Messages::defaults(), new ContextSerializer($router), $this->logger))->handle(
            $request,
        );
    }

    private function connect(): GatewayResponse
    {
        return (
            $this->handle(new GatewayRequest(ConnectionCode::Connect, terminal: $this->terminal)) ?? self::fail(
                'No answer',
            )
        );
    }

    /**
     * Answer to a key pressed after the previous answer, with its context, as the gateway sends it
     *
     * @param list<string> $content
     */
    private function press(GatewayResponse $previous, FunctionKey $key, array $content = []): GatewayResponse
    {
        return (
            $this->handle(new GatewayRequest($key, $content, $previous->context, $this->terminal)) ?? self::fail(
                'No answer',
            )
        );
    }

    /**
     * Answer when the menu goes to route
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
     * Message and context of the error logged
     *
     * @return array{string, array<mixed>}
     */
    private function error(): array
    {
        foreach ($this->logger->records as [$level, $message, $context]) {
            if ($level === LogLevel::ERROR) {
                return [$message, $context];
            }
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
        return Messages::defaults()->get(FrameworkMessage::Error);
    }

    /**
     * Status line the answer shows
     */
    private static function line0(?GatewayResponse $answer): string
    {
        return $answer?->vdt->screen()->line(0) ?? self::fail('No answer');
    }

    /**
     * Videotex content of an answer
     */
    private function contentOf(?GatewayResponse $answer): string
    {
        return $answer?->vdt->toString() ?? self::fail('No answer');
    }
}
