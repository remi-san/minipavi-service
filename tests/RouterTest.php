<?php

declare(strict_types=1);

namespace MiniPaviService\Tests;

use MiniPaviService\Controller;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;
use MiniPaviService\Router;
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
