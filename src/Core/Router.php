<?php

declare(strict_types=1);

namespace MiniPaviService\Core;

use MiniPaviService\Controller;
use MiniPaviService\Controller\Parameters\Parameters;
use MiniPaviService\Controller\State;

final class Router
{
    /** @var array<string, Route<State, Parameters>> */
    private array $routes = [];

    public function __construct(
        private ?string $defaultRoute = null,
    ) {}

    /**
     * Adds a route, binding its controller with the types of what it keeps (Route)
     *
     * @template TState of State
     * @template TParameters of Parameters
     *
     * @param Controller<TState, TParameters> $controller
     */
    public function addRoute(string $name, Controller $controller): void
    {
        $this->routes[$name] = new Route($controller);
    }

    public function setDefaultRoute(string $name): void
    {
        $this->defaultRoute = $name;
    }

    public function has(string $name): bool
    {
        return isset($this->routes[$name]);
    }

    /**
     * @return Route<State, Parameters>
     *
     * @throws \LogicException if there is no such route
     */
    public function route(string $name): Route
    {
        return (
            $this->routes[$name] ?? throw new \LogicException("Unknown route \"{$name}\" (routes: {$this->names()})")
        );
    }

    /**
     * Route of new connections
     *
     * @throws \LogicException if it was not set, or is not a route
     */
    public function defaultRoute(): string
    {
        $route = $this->defaultRoute;
        if ($route === null) {
            throw new \LogicException('No default route: call Router::setDefaultRoute()');
        }
        if (!$this->has($route)) {
            throw new \LogicException("Default route \"{$route}\" is not a route (routes: {$this->names()})");
        }

        return $route;
    }

    private function names(): string
    {
        return implode(', ', array_keys($this->routes));
    }
}
