<?php

declare(strict_types=1);

namespace MiniPaviService;

final class Router
{
    /**
     * @param array<string, Controller> $routes
     */
    public function __construct(
        private array $routes = [],
        private ?string $defaultRoute = null,
    ) {}

    public function addRoute(string $name, Controller $controller): void
    {
        $this->routes[$name] = $controller;
    }

    public function setDefaultRoute(string $name): void
    {
        $this->defaultRoute = $name;
    }

    public function route(string $name): Controller
    {
        return $this->routes[$name] ?? throw new \RuntimeException('route not found');
    }

    public function defaultRoute(): string
    {
        return $this->defaultRoute ?? throw new \LogicException('No default route: call Router::setDefaultRoute()');
    }
}
