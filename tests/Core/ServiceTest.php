<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Core;

use MiniPaviService\Controller;
use MiniPaviService\Controller\CallMode;
use MiniPaviService\Controller\ControllerState;
use MiniPaviService\Controller\NoParameters;
use MiniPaviService\Controller\PageParameters;
use MiniPaviService\Controller\Parameters;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;
use MiniPaviService\Core\Context;
use MiniPaviService\Core\Router;
use MiniPaviService\Core\Service;
use MiniPaviService\Core\Terminal;
use MiniPaviService\Gateway\GatewayAnswer;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Page\InputCommand;
use MiniPaviService\Tests\Support\CallbackController;
use MiniPaviService\Tests\Support\ClearAndShow;
use MiniPaviService\Tests\Support\MemoryLogger;
use MiniPaviService\Vdt\VdtHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

final class ServiceTest extends TestCase
{
    /** @var array<string, list<Request<ControllerState, Parameters>>> requests each controller ran on, by route */
    private array $requests = [];

    private Router $router;

    private MemoryLogger $logger;

    protected function setUp(): void
    {
        $this->logger = new MemoryLogger();
        // menu: Envoi goes to the directory, at page 1; directory: shows its page, reads a code
        $this->router = new Router();
        $this->router->addRoute('menu', $this->controller('menu', NoParameters::class, fn(Request $request) => $request->key() === 'ENVOI'
            ? Response::goTo('directory', new PageParameters(1))
            : Response::display('menu')));
        $this->router->addRoute('directory', $this->controller('directory', PageParameters::class, fn(Request $request) => $request->state() === ControllerState::ReadInput
            ? Response::display('code ' . $request->contentAsString())
            : Response::display('directory', parameters: new PageParameters(2))));
        $this->router->addRoute('broken', $this->controller('broken', NoParameters::class, fn(Request $request) => throw new \RuntimeException('Template data missing')));
        // Two controllers changing to each other
        $this->router->addRoute('ping', $this->controller('ping', NoParameters::class, fn(Request $request) => Response::goTo('pong')));
        $this->router->addRoute('pong', $this->controller('pong', NoParameters::class, fn(Request $request) => Response::goTo('ping')));
        // Keeps parameters of another class than it declares
        $this->router->addRoute('mistyped', $this->controller('mistyped', NoParameters::class, fn(Request $request) => Response::display('x', parameters: new PageParameters(1))));
        $this->router->setDefaultRoute('menu');
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
        self::assertNull($this->handle(new GatewayRequest('FIN', [], $context)));
        self::assertSame([], $this->requests);
        self::assertSame([[LogLevel::INFO, 'Disconnection', []]], $this->logger->records);
    }

    public function testConnectionStartsAtTheDefaultRoute(): void
    {

        self::assertSame('menu', $this->contentOf($this->handle(new GatewayRequest('CNX', ['typed before'], 'not a context'))));
        // A new session: at init, and without what was typed before
        self::assertSame(ControllerState::Init, $this->requests['menu'][0]->state());
        self::assertSame([], $this->requests['menu'][0]->content());
        self::assertSame([[LogLevel::INFO, 'Connection', ['fctn' => 'CNX']]], $this->logger->records);
    }

    public function testKeyRunsTheControllerOfTheContext(): void
    {

        self::assertSame('code 3615', $this->contentOf($this->handle(new GatewayRequest('ENVOI', ['36', '15'], '{"route":"directory","states":{"directory":"read-input"},"parameters":{}}'))));
    }

    public function testErrorIsLoggedAndAnsweredOnLine0WithANewContext(): void
    {
        $answer = $this->handle(new GatewayRequest('ENVOI', [], '{"route":"broken","states":{"broken":"read-input","menu":"read-input"},"parameters":{}}'));

        self::assertSame(VdtHelper::writeLine0(Service::ERROR_MESSAGE), $this->contentOf($answer));
        self::assertNull($answer?->command);
        self::assertSame('{"route":"menu","states":{},"parameters":{}}', $answer?->context);
        [[$level, $message, $context]] = $this->logger->records;
        self::assertSame([LogLevel::ERROR, 'Error serving the request: Template data missing'], [$level, $message]);
        self::assertInstanceOf(\RuntimeException::class, $context['exception'] ?? null);
    }

    public function testNextKeyAfterAnErrorShowsTheDefaultRoute(): void
    {
        $context = $this->handle(new GatewayRequest('ENVOI', [], '{"route":"broken","states":{},"parameters":{}}'))?->context;

        self::assertSame('menu', $this->contentOf($this->handle(new GatewayRequest('SOMMAIRE', [], $context ?? ''))));
        self::assertSame(ControllerState::Init, $this->requests['menu'][0]->state());
    }

    public function testInvalidContextIsAnError(): void
    {

        self::assertSame(VdtHelper::writeLine0(Service::ERROR_MESSAGE), $this->contentOf($this->handle(new GatewayRequest('ENVOI', [], 'not a context'))));
        self::assertSame(LogLevel::ERROR, $this->logger->records[0][0] ?? null);
    }

    public function testErrorOfTheDefaultRouteIsAnsweredOnce(): void
    {
        // On a connection, the default controller fails: no loop, an error answer
        $this->router->setDefaultRoute('broken');

        self::assertSame(VdtHelper::writeLine0(Service::ERROR_MESSAGE), $this->contentOf($this->handle(new GatewayRequest('CNX', [], ''))));
        self::assertCount(1, $this->requests['broken']);
    }

    public function testErrorMessageCanBeChanged(): void
    {

        self::assertSame(VdtHelper::writeLine0('Oops'), $this->contentOf($this->handle(new GatewayRequest('ENVOI', [], 'not a context'), 'Oops')));
    }

    public function testEndlessPageChangesAreStopped(): void
    {
        $context = new Context($this->router, 'ping');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Too many page changes in one request (more than 10): ping → pong → ping → pong → ping → pong → ping → pong → ping → pong → ping → pong');

        $this->serve($context, 'ENVOI', []);
    }

    public function testEndlessPageChangesAreAnsweredAsAnError(): void
    {

        self::assertSame(VdtHelper::writeLine0(Service::ERROR_MESSAGE), $this->contentOf($this->handle(new GatewayRequest('ENVOI', [], '{"route":"ping","states":{},"parameters":{}}'))));
        self::assertCount(Service::MAX_PAGE_CHANGES + 1, array_merge($this->requests['ping'], $this->requests['pong']));
    }

    public function testChangePageStartsTheTargetAtInitWithoutTheInput(): void
    {
        // The directory was left reading a code: it must not read the menu's "1" as one
        $context = $this->context('{"route":"menu","states":{"directory":"read-input","menu":"read-input"},"parameters":{"directory":{"page":5}}}');

        $answer = $this->serve($context, 'ENVOI', ['1']);

        self::assertSame('directory', $answer);
        $directory = $this->requests['directory'][0];
        self::assertSame(ControllerState::Init, $directory->state());
        self::assertEquals(new PageParameters(1), $directory->parameters());
        self::assertSame('', $directory->key());
        self::assertSame([], $directory->content());
        self::assertEquals(new Terminal('192.0.2.1', 'websocket', 'Cv5'), $directory->terminal());
    }

    public function testChangePageResetsTheControllerLeft(): void
    {
        $context = $this->context('{"route":"menu","states":{"menu":"read-input"},"parameters":{}}');

        $this->serve($context, 'ENVOI', ['1']);

        self::assertSame('directory', $context->route());
        self::assertSame(ControllerState::ReadInput, $context->state());
        self::assertEquals(new PageParameters(2), $context->parameters());
        $context->changeRoute('menu');
        self::assertSame(ControllerState::Init, $context->state());
        self::assertEquals(new NoParameters(), $context->parameters());
    }

    public function testUnknownRouteStartsAgainAtTheDefaultRoute(): void
    {
        // E.g. a route removed in a new version of the service
        $context = $this->context('{"route":"removed","states":{"removed":"read-input","directory":"read-input"},"parameters":{"directory":{"page":5}}}');

        $answer = $this->serve($context, 'ENVOI', ['1']);

        self::assertSame('menu', $answer);
        $menu = $this->requests['menu'][0];
        self::assertSame(ControllerState::Init, $menu->state());
        self::assertSame(['', []], [$menu->key(), $menu->content()]);
        // As a new connection: nothing kept from the former context
        $context->changeRoute('directory');
        self::assertSame(ControllerState::Init, $context->state());
        self::assertEquals(new PageParameters(), $context->parameters());
        self::assertSame([[LogLevel::WARNING, 'Unknown route in the context, starting again at the default route', ['route' => 'removed']]], $this->logger->records);
    }

    public function testDisplayIsAnsweredWithTheContextKeepingItsStateAndParameters(): void
    {
        $answer = $this->answerTo(Response::display('Hello', ControllerState::Display, new PageParameters(2)));

        // To this service (next: null), waiting for the user
        self::assertEquals(new GatewayAnswer('Hello', '{"route":"answer","states":{"answer":"display"},"parameters":{"answer":{"page":2}}}'), $answer);
    }

    public function testReadInputIsAnsweredWithTheCommand(): void
    {
        $command = new InputCommand(['COMMAND' => ['name' => 'InputTxt', 'param' => ['x' => 1]]]);

        $answer = $this->answerTo(Response::readInput('Code ?', $command));

        self::assertSame('Code ?', $answer->vdt);
        self::assertSame(['COMMAND' => ['name' => 'InputTxt', 'param' => ['x' => 1]]], $answer->command);
        self::assertNull($answer->next);
        self::assertNull($answer->directCall);
        self::assertSame('{"route":"answer","states":{"answer":"read-input"},"parameters":{"answer":{"page":0}}}', $answer->context);
    }

    public function testReadInputWithoutCommandOnlySendsTheContent(): void
    {
        // Page::inputCommand() gives null for a page without <input>
        $answer = $this->answerTo(Response::readInput('Hello', null));

        self::assertSame('Hello', $answer->vdt);
        self::assertNull($answer->command);
    }

    public function testConnectToIsAnsweredWithTheOtherService(): void
    {
        $answer = $this->answerTo(Response::connectTo('https://example.org/service', 'Cnx en cours', parameters: new PageParameters(4)));

        self::assertSame('Cnx en cours', $answer->vdt);
        self::assertSame('https://example.org/service', $answer->next);
        self::assertSame(CallMode::Connect, $answer->directCall);
        // Kept for when the user comes back
        self::assertSame('{"route":"answer","states":{"answer":"init"},"parameters":{"answer":{"page":4}}}', $answer->context);
    }

    public function testAResponseOfItsOwnNeedsNoChangeToTheService(): void
    {
        $answer = $this->answerTo(new ClearAndShow('Hello'));

        self::assertSame(VdtHelper::clearScreen() . 'Hello', $answer->vdt);
        // Its state and parameters are kept, as for any response
        self::assertSame('{"route":"answer","states":{"answer":"display"},"parameters":{"answer":{"page":1}}}', $answer->context);
    }

    public function testKeepingParametersOfAnotherClassIsAnError(): void
    {
        $answer = $this->handle(new GatewayRequest('ENVOI', [], '{"route":"mistyped","states":{},"parameters":{}}'));

        self::assertSame(VdtHelper::writeLine0(Service::ERROR_MESSAGE), $this->contentOf($answer));
        self::assertStringContainsString('keeps parameters of MiniPaviService\\Controller\\NoParameters, not MiniPaviService\\Controller\\PageParameters', $this->logger->records[0][1] ?? '');
    }

    public function testInputGoesToTheControllerOfTheCurrentRoute(): void
    {
        $context = $this->context('{"route":"directory","states":{"directory":"read-input"},"parameters":{}}');

        self::assertSame('code 3615', ($this->serve($context, 'ENVOI', ['36', '15'])));
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
        return new CallbackController($parametersType, function (Request $request) use ($route, $run): Response {
            $this->requests[$route][] = $request;

            return $run($request);
        });
    }

    /**
     * Context of the service, from its JSON
     */
    private function context(string $serialized): Context
    {
        return Context::unserialize($serialized, $this->router);
    }

    /**
     * Answer of the service when the controller of its route returns response
     */
    private function answerTo(Response $response): GatewayAnswer
    {
        $this->router->addRoute('answer', $this->controller('answer', PageParameters::class, fn(Request $request) => $response));

        return (new Service($this->router, $this->logger))->serve(new Context($this->router, 'answer'), 'ENVOI', [], new Terminal());
    }

    /**
     * Service::handle() for a request of the gateway
     */
    private function handle(GatewayRequest $request, ?string $errorMessage = null): ?GatewayAnswer
    {
        $service = $errorMessage === null
            ? new Service($this->router, $this->logger)
            : new Service($this->router, $this->logger, $errorMessage);

        return $service->handle($request);
    }

    /**
     * Videotex content of an answer
     */
    private function contentOf(?GatewayAnswer $answer): string
    {
        if ($answer === null) {
            self::fail('No answer');
        }

        return $answer->vdt;
    }

    /**
     * Runs the controllers for this input, and gives the Videotex content of the answer
     *
     * @param list<string> $content
     */
    private function serve(Context $context, string $key, array $content): string
    {
        return (new Service($this->router, $this->logger))->serve($context, $key, $content, new Terminal('192.0.2.1', 'websocket', 'Cv5'))->vdt;
    }
}
