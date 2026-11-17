<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Testing;

use MiniPaviService\Controller\ControllerState;
use MiniPaviService\Controller\Parameters\NoParameters;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;
use MiniPaviService\Core\Router;
use MiniPaviService\Core\Service;
use MiniPaviService\Gateway\CallMode;
use MiniPaviService\Message\Messages;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Testing\ServiceTester;
use MiniPaviService\Tests\Support\CallbackController;
use MiniPaviService\Vdt\Vdt;
use MiniPaviService\Vdt\VdtHelper;
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
        $router = new Router();
        $router->addRoute('menu', $this->controller(fn(Request $request) => match ($request->contentAsString()) {
            '2' => Response::goTo('directory'),
            'ext' => Response::connectTo('https://example.org/', VdtHelper::writeLine0('Connexion…')),
            default => Response::display(Vdt::join(
                VdtHelper::clearScreen(),
                VdtHelper::setPos(1, 1),
                Vdt::text('MENU'),
            )),
        }));
        $router->addRoute('directory', $this->controller(fn(Request $request) => $request->functionKey()
            === FunctionKey::Next
                ? Response::display(VdtHelper::writeLine0('Dernière page'))
                : Response::display(Vdt::join(
                    VdtHelper::clearScreen(),
                    VdtHelper::setPos(1, 2),
                    Vdt::text('Annuaire'),
                ))));
        $router->setDefaultRoute('menu');

        $this->tester = new ServiceTester(
            new Service($router, Messages::defaults()),
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
            ->assertLineContains(2, 'Annuaire');

        self::assertSame(['2'], $this->requests[1]->content());
        self::assertEquals(new Terminal('192.0.2.1', 'websocket', 'Cv5'), $this->requests[1]->terminal());
    }

    public function testScreenKeepsWhatAnswersDontClear(): void
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
        $this->tester->connect()->type('a', 'b')->press(FunctionKey::Send)->press(FunctionKey::Send);

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

        self::assertSame(CallMode::Connect, $this->tester->answer()->directCall);
    }

    public function testDisconnectIsNotAnswered(): void
    {
        // Fails if the service answers it
        $this->tester->connect()->disconnect();
    }

    public function testFailureShowsTheScreen(): void
    {
        try {
            $this->tester->connect()->assertScreenContains('Annuaire');
        } catch (AssertionFailedError $e) {
            self::assertStringContainsString("The screen doesn't show \"Annuaire\". Screen:\n", $e->getMessage());
            self::assertStringContainsString(' 1|MENU' . str_repeat(' ', 36) . '|', $e->getMessage());

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
        return new CallbackController(NoParameters::class, function (Request $request) use ($run): Response {
            $this->requests[] = $request;

            return $run($request);
        });
    }
}
