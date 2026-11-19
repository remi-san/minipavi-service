<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Testing;

use MiniPaviService\Controller\ControllerState;
use MiniPaviService\Controller\Parameters\NoParameters;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;
use MiniPaviService\Core\ContextSerializer;
use MiniPaviService\Core\RouterBuilder;
use MiniPaviService\Core\Service;
use MiniPaviService\Message\MessagesXmlParser;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Testing\ServiceTester;
use MiniPaviService\Tests\Support\CallbackController;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;

final class ServiceTesterTest extends TestCase
{
    private ServiceTester $tester;

    /** @var list<Request<ControllerState, NoParameters>> requests the controllers ran on */
    private array $requests = [];

    protected function setUp(): void
    {
        // menu: a code, Envoi goes to the directory ("2") or to another service ("ext");
        // directory: Suite on its only page shows a message on line 0
        $routes = new RouterBuilder('menu');
        $routes->addRoute('menu', $this->controller(static fn(Request $request) => match ($request->contentAsString()) {
            '2' => Response::goTo('directory'),
            'ext' => Response::connectTo('https://example.org/', Vdt::line0('Connexion…')),
            default => Response::display(Vdt::join(Vdt::clearScreen(), Vdt::position(1, row: 1), Vdt::text('MENU'))),
        }));
        $routes->addRoute('directory', $this->controller(static fn(Request $request) => $request->functionKey()
            === FunctionKey::Next
                ? Response::display(Vdt::line0('Dernière page'))
                : Response::display(Vdt::join(Vdt::clearScreen(), Vdt::position(1, row: 2), Vdt::text('Annuaire')))));
        $router = $routes->build();

        $serializer = new ContextSerializer($router);
        $this->tester = new ServiceTester(
            new Service($router, (new MessagesXmlParser())->defaults(), $serializer),
            $serializer,
            new Terminal('192.0.2.1', 'websocket', 'Cv5'),
        );
    }

    public function testScriptsASessionKeepingTheContext(): void
    {
        $this->tester
            ->connect()
            ->assertRoute('menu')
            ->assertScreenContains('MENU')
            ->type('2')
            ->press(FunctionKey::Send)
            ->assertRoute('directory')
            ->assertScreenContains('Annuaire')
            ->assertScreenNotContains('MENU')
            ->assertLineContains(2, text: 'Annuaire');

        self::assertSame(['2'], $this->requests[1]->content());
        self::assertEquals(new Terminal('192.0.2.1', 'websocket', 'Cv5'), $this->requests[1]->terminal());
    }

    public function testScreenKeepsWhatResponsesDontClear(): void
    {
        $this->tester
            ->connect()
            ->type('2')
            ->press(FunctionKey::Send)
            ->press(FunctionKey::Next)
            ->assertLine0('Dernière page')
            // The page drawn before is still there
            ->assertScreenContains('Annuaire');
    }

    public function testTypedTextIsOnlyForTheNextKey(): void
    {
        $this->tester->connect()->type(...['a', 'b'])->press(FunctionKey::Send)->press(FunctionKey::Send);

        self::assertSame(['a', 'b'], $this->requests[1]->content());
        self::assertSame([], $this->requests[2]->content());
    }

    public function testConnectStartsANewSession(): void
    {
        $this->tester
            ->connect()
            ->type('2')
            ->press(FunctionKey::Send)
            ->connect()
            ->assertRoute('menu')
            ->assertScreenNotContains('Annuaire');
    }

    public function testAssertsTheUserIsSentToAnotherService(): void
    {
        $this->tester->connect()->type('ext')->press(FunctionKey::Send)->assertConnectsTo('https://example.org/');
    }

    public function testFailsWhenTheUserIsSentToAnotherService(): void
    {
        $this->tester->connect()->type('ext')->press(FunctionKey::Send);

        try {
            $this->tester->assertConnectsTo('https://example.org/other');
        } catch (AssertionFailedError $e) {
            self::assertStringContainsString('Not connected at once to the expected service', $e->getMessage());

            return;
        }
        self::fail('The assertion passed');
    }

    public function testFailsWhenTheUserStaysInTheService(): void
    {
        $this->expectException(AssertionFailedError::class);

        $this->tester->connect()->assertConnectsTo('https://example.org/');
    }

    public function testDisconnectGetsNoResponse(): void
    {
        // Fails if the service responds to it
        $this->tester->connect()->disconnect();
    }

    public function testFailureShowsTheScreen(): void
    {
        try {
            $this->tester->connect()->assertScreenContains('Annuaire');
        } catch (AssertionFailedError $e) {
            self::assertStringContainsString("The screen doesn't show \"Annuaire\". Screen:\n", $e->getMessage());
            self::assertStringContainsString(' 1|MENU' . str_repeat(' ', times: 36) . '|', $e->getMessage());

            return;
        }
        self::fail('The assertion passed');
    }

    public function testFailsOnTheWrongRoute(): void
    {
        $this->expectException(AssertionFailedError::class);

        $this->tester->connect()->assertRoute('directory');
    }

    public function testKeyBeforeConnectingIsAnError(): void
    {
        $this->expectException(\LogicException::class);

        $this->tester->press(FunctionKey::Send);
    }

    /**
     * Controller without parameters, recording the requests it runs on, in $this->requests
     *
     * @param \Closure(Request<ControllerState, NoParameters>): Response $run
     *
     * @return CallbackController<NoParameters>
     */
    private function controller(\Closure $run): CallbackController
    {
        return new CallbackController(
            NoParameters::class,
            /** @param Request<ControllerState, NoParameters> $request */
            function (Request $request) use ($run): Response {
                $this->requests[] = $request;

                return $run($request);
            },
        );
    }
}
