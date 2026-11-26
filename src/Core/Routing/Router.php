<?php

declare(strict_types=1);

namespace MiniPaviService\Core\Routing;

use MiniPaviService\Controller\Parameters\Parameters;
use MiniPaviService\Controller\State;

/**
 * The routes of a service, each with its controller bound to the types of what it keeps
 * (Route), and the one new connections start at. Immutable, built by RouterBuilder: its
 * default route is always one of its routes, a service whose default route isn't one failing
 * when its router is built, at boot.
 */
final readonly class Router
{
    /**
     * @param string $defaultRoute route of new connections, one of routes
     * @param array<string, Route<State, Parameters>> $routes by name
     *
     * @throws \LogicException if the default route isn't one of the routes
     */
    public function __construct(
        private string $defaultRoute,
        private array $routes,
    ) {
        if (!$this->has($defaultRoute)) {
            throw new \LogicException("Default route \"{$defaultRoute}\" is not a route (routes: {$this->names()})");
        }
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->routes);
    }

    /**
     * @return Route<State, Parameters>
     *
     * @throws UnknownRouteException if there is no such route
     */
    public function route(string $name): Route
    {
        return $this->routes[$name] ?? throw new UnknownRouteException($name, array_keys($this->routes));
    }

    /**
     * Route of new connections
     */
    public function defaultRoute(): string
    {
        return $this->defaultRoute;
    }

    private function names(): string
    {
        return implode(', ', array_keys($this->routes));
    }
}
