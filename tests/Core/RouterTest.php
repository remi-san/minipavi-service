<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Core;

use MiniPaviService\Controller\ControllerState;
use MiniPaviService\Controller\Pagination\PageParameters;
use MiniPaviService\Controller\Parameters\NoParameters;
use MiniPaviService\Controller\Parameters\Values;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response\DisplayPage;
use MiniPaviService\Core\RouterBuilder;
use MiniPaviService\Core\UnknownRouteException;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Tests\Support\CallbackController;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testGivesTheRouteOfItsController(): void
    {
        $router = (new RouterBuilder('home'))
            ->addRoute(
                'home',
                new CallbackController(
                    PageParameters::class,
                    /** @param Request<ControllerState, PageParameters> $request */
                    static fn(Request $request) => new DisplayPage(Vdt::text('home ' . $request->parameters()->page)),
                ),
            )
            ->build();

        $route = $router->route('home');
        self::assertSame(ControllerState::Init, $route->start()->state());
        self::assertEquals(
            new PageParameters(3),
            $route->restore('read-input', new Values(['page' => 3]))->parameters(),
        );
        self::assertEquals(new DisplayPage(new Vdt('home 0')), $route->start()->run(null, [], new Terminal()));
        self::assertTrue($router->has('home'));
        self::assertFalse($router->has('menu'));
        self::assertSame('home', $router->defaultRoute());
    }

    public function testDefaultRouteMustBeARoute(): void
    {
        $builder = (new RouterBuilder('hme'))
            ->addRoute('home', self::controller())
            ->addRoute('menu', self::controller());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Default route "hme" is not a route (routes: home, menu)');

        $builder->build();
    }

    public function testUnknownRouteIsNamed(): void
    {
        $router = (new RouterBuilder('home'))
            ->addRoute('home', self::controller())
            ->build();

        $this->expectException(UnknownRouteException::class);
        $this->expectExceptionMessage('Unknown route "directory" (routes: home)');

        $router->route('directory');
    }

    /**
     * @return CallbackController<NoParameters>
     */
    private static function controller(): CallbackController
    {
        return new CallbackController(
            NoParameters::class,
            /** @param Request<ControllerState, NoParameters> $request */
            static fn(Request $request) => throw new \LogicException('not run'),
        );
    }
}
