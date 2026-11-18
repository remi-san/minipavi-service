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
     * What each route keeps, by route (those whose controller ran or was restored): the value
     * of its state, and its parameters as values (Parameters::toArray()), e.g. to serialize them
     *
     * @return array<string, array{state: int|string, parameters: array<string, mixed>}>
     */
    public function kept(): array
    {
        return array_map(static fn(RouteContext $route) => [
            'state' => $route->state()->value,
            'parameters' => $route->parameters()->toArray(),
        ], $this->routes);
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
     * Leaves the current route for another (Response::goTo()): the controller left starts
     * again next time (it keeps nothing), and the one of route starts again now, at its initial
     * state, with parameters (null: their defaults), whatever either kept before
     *
     * @param ?Parameters $parameters of the class route's controller declares
     *
     * @throws \LogicException for parameters of another class than the controller's
     */
    public function leaveFor(string $route, ?Parameters $parameters = null): void
    {
        $this->keep(state: null, parameters: null);
        $this->route = $route;
        $this->keep(state: null, parameters: $parameters);
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
    public function run(?Key $key, array $content, Terminal $terminal): Response
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
