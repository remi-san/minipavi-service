<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Core;

use MiniPaviService\Controller\ControllerState;
use MiniPaviService\Controller\Pagination\PageParameters;
use MiniPaviService\Controller\Parameters\NoParameters;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;
use MiniPaviService\Core\Context;
use MiniPaviService\Core\Router;
use MiniPaviService\Core\RouterBuilder;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Tests\Support\CallbackController;
use MiniPaviService\Tests\Support\FromParameters;
use MiniPaviService\Tests\Support\RecordingFlow;
use MiniPaviService\Tests\Support\SearchController;
use MiniPaviService\Tests\Support\SearchState;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\TestCase;

final class ContextTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        // home: built-in states, a page; search: states of its own, a page; menu: nothing;
        // origin: the route it was opened from
        $routes = new RouterBuilder('home');
        $routes->addRoute(
            'home',
            new CallbackController(
                PageParameters::class,
                /** @param Request<ControllerState, PageParameters> $request */
                static fn(Request $request) => Response::display(Vdt::text(
                    'home '
                        . $request->state()->value
                        . ' '
                        . $request->parameters()->page
                        . ' '
                        . $request->key()
                        . ' '
                        . $request->contentAsString()
                        . ' '
                        . $request->terminal()->remoteAddr(),
                )),
            ),
        );
        $routes->addRoute('search', new SearchController());
        $routes->addRoute('menu', new CallbackController(NoParameters::class, static fn(
            Request $request,
        ) => Response::display(new Vdt('menu'))));
        $routes->addRoute(
            'origin',
            new CallbackController(
                FromParameters::class,
                /** @param Request<ControllerState, FromParameters> $request */
                static fn(Request $request) => Response::display(Vdt::text('from ' . $request->parameters()->from)),
            ),
        );
        $this->router = $routes->build();
    }

    public function testNewRouteHasTheInitialStateAndDefaultParametersOfItsController(): void
    {
        $context = new Context($this->router, 'search');

        self::assertSame(SearchState::Init, $context->state());
        self::assertEquals(new PageParameters(), $context->parameters());
    }

    public function testRunsTheControllerOnWhatItKeptAndTheInput(): void
    {
        $context = new Context($this->router, 'home');
        $context->keep(ControllerState::ReadInput, new PageParameters(2));

        self::assertSame(
            'home read-input 2 ENVOI abc 192.0.2.1',
            $context
                ->run('ENVOI', ['abc'], new Terminal('192.0.2.1'))
                ->continueWith(new RecordingFlow())
                ->vdt->toString(),
        );
    }

    public function testKeepsStateAndParametersPerRoute(): void
    {
        $context = new Context($this->router, 'home');
        $context->keep(ControllerState::ReadInput, new PageParameters(2));
        $context->changeRoute('search');

        self::assertSame(SearchState::Init, $context->state());

        $context->changeRoute('home');
        self::assertSame(ControllerState::ReadInput, $context->state());
        self::assertEquals(new PageParameters(2), $context->parameters());
    }

    public function testKeepingNothingResetsTheCurrentRoute(): void
    {
        $context = new Context($this->router, 'search');
        $context->keep(SearchState::ReadQuery, new PageParameters(2));
        $context->keep(null, parameters: null);

        self::assertSame(SearchState::Init, $context->state());
        self::assertEquals(new PageParameters(), $context->parameters());
    }

    public function testKeepingAnotherTypeThanTheControllersIsAnError(): void
    {
        $context = new Context($this->router, 'search');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('keeps states of ' . SearchState::class . ', not ' . ControllerState::class);

        $context->keep(ControllerState::ReadInput, parameters: null);
    }

    public function testKeepingParametersOfAnotherClassThanTheControllersIsAnError(): void
    {
        $context = new Context($this->router, 'search');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(
            SearchController::class
            . ' keeps parameters of '
            . PageParameters::class
            . ', not '
            . FromParameters::class,
        );

        $context->keep(null, new FromParameters('home'));
    }
}
