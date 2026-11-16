<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Core;

use MiniPaviService\Controller;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;
use MiniPaviService\Core\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testGivesTheControllerOfARoute(): void
    {
        $controller = self::controller();
        $router = new Router();
        $router->addRoute('home', $controller);
        $router->setDefaultRoute('home');

        self::assertSame($controller, $router->route('home'));
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

    private static function controller(): Controller
    {
        return new class implements Controller {
            public function run(Request $request): Response
            {
                throw new \LogicException('not run');
            }
        };
    }
}
