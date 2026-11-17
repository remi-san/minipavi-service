<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Core;

use MiniPaviService\Controller\ControllerState;
use MiniPaviService\Controller\NoParameters;
use MiniPaviService\Controller\PageParameters;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;
use MiniPaviService\Controller\Values;
use MiniPaviService\Core\Router;
use MiniPaviService\Core\Terminal;
use MiniPaviService\Tests\Support\CallbackController;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testGivesTheRouteOfItsController(): void
    {
        $router = new Router();
        $router->addRoute('home', new CallbackController(PageParameters::class, fn(Request $request) => Response::display('home ' . $request->parameters()->page)));
        $router->setDefaultRoute('home');

        $route = $router->route('home');
        self::assertSame(ControllerState::Init, $route->start()->state());
        self::assertEquals(new PageParameters(3), $route->restore('read-input', new Values(['page' => 3]))->parameters());
        self::assertEquals(Response::display('home 0'), $route->start()->run('', [], new Terminal()));
        self::assertTrue($router->has('home'));
        self::assertFalse($router->has('menu'));
        self::assertSame('home', $router->defaultRoute());
    }

    public function testDefaultRouteMustBeSet(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No default route: call Router::setDefaultRoute()');

        (new Router())->defaultRoute();
    }

    public function testDefaultRouteMustBeARoute(): void
    {
        $router = new Router();
        $router->setDefaultRoute('hme');
        $router->addRoute('home', self::controller());
        $router->addRoute('menu', self::controller());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Default route "hme" is not a route (routes: home, menu)');

        $router->defaultRoute();
    }

    public function testUnknownRouteIsNamed(): void
    {
        $router = new Router();
        $router->addRoute('home', self::controller());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Unknown route "directory" (routes: home)');

        $router->route('directory');
    }

    /**
     * @return CallbackController<NoParameters>
     */
    private static function controller(): CallbackController
    {
        return new CallbackController(NoParameters::class, fn(Request $request) => throw new \LogicException('not run'));
    }
}
