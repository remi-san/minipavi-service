<?php

declare(strict_types=1);

namespace MiniPaviService\Core;

use MiniPaviService\Controller;
use MiniPaviService\Controller\Parameters\Parameters;
use MiniPaviService\Controller\State;

/**
 * Builds the Router of a service: its default route, then its routes, one by one (e.g. by a
 * container, with lazy controllers); build() fails if the default route isn't one of them
 */
final class RouterBuilder
{
    /** @var array<string, Route<State, Parameters>> */
    private array $routes = [];

    /**
     * @param string $defaultRoute route of new connections, one of the routes added
     */
    public function __construct(
        private readonly string $defaultRoute,
    ) {}

    /**
     * Adds a route, binding its controller with the types of what it keeps (Route)
     *
     * @template TState of State
     * @template TParameters of Parameters
     *
     * @param Controller<TState, TParameters> $controller
     */
    public function addRoute(string $name, Controller $controller): self
    {
        $this->routes[$name] = new Route($controller);

        return $this;
    }

    /**
     * @throws \LogicException if the default route isn't one of the routes added
     */
    public function build(): Router
    {
        return new Router($this->defaultRoute, $this->routes);
    }
}
