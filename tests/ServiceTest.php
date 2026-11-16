<?php

declare(strict_types=1);

namespace MiniPaviService\Tests;

use MiniPaviService\Context;
use MiniPaviService\Controller;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;
use MiniPaviService\MiniPaviClient;
use MiniPaviService\RequestUrl;
use MiniPaviService\Router;
use MiniPaviService\Service;
use MiniPaviService\Terminal;
use MiniPaviService\Tests\Support\MemoryLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

final class ServiceTest extends TestCase
{
    /** @var array<string, list<Request>> requests each controller ran on, by route */
    private array $requests = [];

    private Router $router;

    private MemoryLogger $logger;

    protected function setUp(): void
    {
        $this->logger = new MemoryLogger();
        // menu: Envoi goes to the directory; directory: shows its page, reads a code
        $this->router = new Router();
        $this->router->addRoute('menu', $this->controller('menu', fn(Request $request) => $request->key() === 'ENVOI'
            ? Response::goTo('directory', ['from' => 'menu'])
            : Response::display('menu')));
        $this->router->addRoute('directory', $this->controller('directory', fn(Request $request) => $request->state() === Controller::STATE_READ_INPUT
            ? Response::display('code ' . $request->contentAsString())
            : Response::display('directory', parameters: ['page' => 2])));
        $this->router->setDefaultRoute('menu');
    }

    public function testChangePageStartsTheTargetAtInitWithoutTheInput(): void
    {
        // The directory was left reading a code: it must not read the menu's "1" as one
        $context = new Context('directory', ['directory' => Controller::STATE_READ_INPUT, 'menu' => Controller::STATE_READ_INPUT], ['directory' => ['page' => 5]]);
        $context->changeRoute('menu');

        $answer = $this->serve($context, 'ENVOI', ['1']);

        self::assertSame('directory', base64_decode($answer));
        $directory = $this->requests['directory'][0];
        self::assertSame(Controller::STATE_INIT, $directory->state());
        self::assertSame(['from' => 'menu'], $directory->parameters());
        self::assertSame('', $directory->key());
        self::assertSame([], $directory->content());
        self::assertEquals(new Terminal('192.0.2.1', 'websocket', 'Cv5'), $directory->terminal());
    }

    public function testChangePageResetsTheControllerLeft(): void
    {
        $context = new Context('menu', ['menu' => Controller::STATE_READ_INPUT], ['menu' => ['choice' => 1]]);

        $this->serve($context, 'ENVOI', ['1']);

        self::assertSame('directory', $context->route());
        self::assertSame(Controller::STATE_READ_INPUT, $context->state());
        self::assertSame(['page' => 2], $context->parameters());
        $context->changeRoute('menu');
        self::assertSame(Controller::STATE_INIT, $context->state());
        self::assertSame([], $context->parameters());
    }

    public function testUnknownRouteStartsAgainAtTheDefaultRoute(): void
    {
        // E.g. a route removed in a new version of the service
        $context = new Context('removed', ['removed' => Controller::STATE_READ_INPUT, 'directory' => Controller::STATE_READ_INPUT], ['directory' => ['page' => 5]]);

        $answer = $this->serve($context, 'ENVOI', ['1']);

        self::assertSame('menu', base64_decode($answer));
        $menu = $this->requests['menu'][0];
        self::assertSame(Controller::STATE_INIT, $menu->state());
        self::assertSame(['', []], [$menu->key(), $menu->content()]);
        // As a new connection: nothing kept from the former context
        $context->changeRoute('directory');
        self::assertSame([Controller::STATE_INIT, []], [$context->state(), $context->parameters()]);
        self::assertSame([[LogLevel::WARNING, 'Unknown route in the context, starting again at the default route', ['route' => 'removed']]], $this->logger->records);
    }

    public function testInputGoesToTheControllerOfTheCurrentRoute(): void
    {
        $context = new Context('directory', ['directory' => Controller::STATE_READ_INPUT]);

        self::assertSame('code 3615', base64_decode($this->serve($context, 'ENVOI', ['36', '15'])));
    }

    /**
     * Controller recording the requests it runs on, in $this->requests
     *
     * @param \Closure(Request): Response $run
     */
    private function controller(string $route, \Closure $run): Controller
    {
        return new class (function (Request $request) use ($route, $run): Response {
            $this->requests[$route][] = $request;

            return $run($request);
        }) implements Controller {
            /**
             * @param \Closure(Request): Response $run
             */
            public function __construct(private \Closure $run) {}

            public function run(Request $request): Response
            {
                return ($this->run)($request);
            }
        };
    }

    /**
     * Runs the controllers for this input, and gives the content of the answer (base64)
     *
     * @param list<string> $content
     */
    private function serve(Context $context, string $key, array $content): string
    {
        $server = $_SERVER;
        $_SERVER['HTTP_HOST'] = 'services';
        $_SERVER['PHP_SELF'] = '/index.php';
        ob_start();
        try {
            (new Service(new MiniPaviClient(new RequestUrl()), $this->router, $this->logger))->serve($context, $key, $content, new Terminal('192.0.2.1', 'websocket', 'Cv5'));
        } finally {
            $output = (string) ob_get_clean();
            $_SERVER = $server;
        }
        $answer = json_decode($output, true);
        if (!\is_array($answer) || !\is_string($answer['content'] ?? null)) {
            self::fail("Not an answer: $output");
        }

        return $answer['content'];
    }
}
