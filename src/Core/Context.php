<?php

declare(strict_types=1);

namespace MiniPaviService\Core;

use MiniPaviService\Controller\Parameters\Parameters;
use MiniPaviService\Controller\Response;
use MiniPaviService\Controller\State;
use MiniPaviService\Minitel\Key;
use MiniPaviService\Minitel\Terminal;

/**
 * User context, sent to the gateway with each answer (ContextSerializer) and given back with
 * the next request: the current route, and the state and parameters each route keeps, typed
 * as its controller declares them (a RouteContext per route). A route without any has its controller's initial
 * state and the defaults of its parameters.
 */
final class Context
{
    /**
     * @param array<string, RouteContext<State, Parameters>> $routes what each route keeps
     */
    public function __construct(
        private Router $router,
        private string $route,
        private array $routes = [],
    ) {}

    /**
     * What each route keeps, by route: those whose controller ran or was restored
     *
     * @return array<string, RouteContext<State, Parameters>>
     */
    public function kept(): array
    {
        return $this->routes;
    }

    public function route(): string
    {
        return $this->route;
    }

    /**
     * Starts again at route, as a new connection: without what the routes kept
     */
    public function restart(string $route): void
    {
        $this->route = $route;
        $this->routes = [];
    }

    /**
     * Goes to route, whose controller starts again: its initial state, and parameters (null:
     * their defaults), whatever it kept before (e.g. Response::goTo())
     *
     * @param ?Parameters $parameters of the class its controller declares
     *
     * @throws \LogicException for parameters of another class than the controller's
     */
    public function enter(string $route, ?Parameters $parameters = null): void
    {
        $this->changeRoute($route);
        $this->keep(null, $parameters);
    }

    /**
     * Goes to route, as it is: with what it kept (or its controller's initial state and the
     * defaults of its parameters)
     */
    public function changeRoute(string $route): void
    {
        $this->route = $route;
    }

    /**
     * State of the current route: the one kept, or its controller's initial state
     */
    public function state(): State
    {
        return $this->current()->state();
    }

    /**
     * Parameters of the current route: the ones kept, or the defaults of its controller's
     */
    public function parameters(): Parameters
    {
        return $this->current()->parameters();
    }

    /**
     * Keeps the state and parameters of the current route (RouteContext::keep())
     *
     * @param ?State $state a case of the enum of its controller's states; null: the initial one
     * @param ?Parameters $parameters of the class its controller declares; null: the defaults
     *
     * @throws \LogicException for a state or parameters of other types than the controller's
     */
    public function keep(?State $state, ?Parameters $parameters): void
    {
        $route = $this->current();
        $route->keep($state, $parameters);
        $this->routes[$this->route] = $route;
    }

    /**
     * Runs the controller of the current route on its state and parameters, and the input of
     * the gateway
     *
     * @param list<string> $content
     */
    public function run(?Key $key = null, array $content = [], Terminal $terminal = new Terminal()): Response
    {
        return $this->current()->run($key, $content, $terminal);
    }

    /**
     * @return RouteContext<State, Parameters>
     */
    private function current(): RouteContext
    {
        return $this->routes[$this->route] ?? $this->router->route($this->route)->start();
    }
}
