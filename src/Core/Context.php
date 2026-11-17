<?php

declare(strict_types=1);

namespace MiniPaviService\Core;

use MiniPaviService\Controller\Parameters\Parameters;
use MiniPaviService\Controller\Parameters\Values;
use MiniPaviService\Controller\Response;
use MiniPaviService\Controller\State;
use MiniPaviService\Minitel\Terminal;

/**
 * User context, sent to the gateway with each answer and given back with the next request:
 * the current route, and the state and parameters each route keeps, typed as its controller
 * declares them (a RouteContext per route). A route without any has its controller's initial
 * state and the defaults of its parameters.
 */
final class Context
{
    /**
     * @param array<string, RouteContext<*, *>> $routes what each route keeps
     */
    public function __construct(
        private Router $router,
        private string $route,
        private array $routes = [],
    ) {}

    /**
     * Context sent with the previous answer (JSON, see serialize()), typed by the router's
     * routes (Route::restore()): a state their enum doesn't have is the initial one, invalid
     * parameters get their defaults, and routes the router doesn't have are dropped. A
     * context without route (e.g. sent by another version of the service) starts at the
     * default route.
     *
     * @throws \RuntimeException if it isn't a JSON object
     */
    public static function unserialize(string $serialized, Router $router): self
    {
        $context = self::decode($serialized);
        $states = \is_array($context['states'] ?? null) ? $context['states'] : [];
        $parameters = \is_array($context['parameters'] ?? null) ? $context['parameters'] : [];

        $routes = [];
        foreach (array_keys($states + $parameters) as $route) {
            if (\is_string($route) && $router->has($route)) {
                $state = \is_string($states[$route] ?? null) ? $states[$route] : null;
                $values = \is_array($parameters[$route] ?? null)
                    ? array_filter($parameters[$route], \is_string(...), ARRAY_FILTER_USE_KEY)
                    : [];
                $routes[$route] = $router->route($route)->restore($state, new Values($values));
            }
        }

        return new self(
            $router,
            \is_string($context['route'] ?? null) ? $context['route'] : $router->defaultRoute(),
            $routes,
        );
    }

    /**
     * The context as JSON: {"route": ..., "states": {route: state}, "parameters": {route:
     * {name: value}}}, states as their enum's value, parameters as Parameters::toArray()
     */
    public function serialize(): string
    {
        try {
            return json_encode(
                [
                    'route' => $this->route,
                    'states' => (object) array_map(fn(RouteContext $route) => $route->state()->value, $this->routes),
                    'parameters' => (object) array_map(
                        fn(RouteContext $route) => (object) $route->parameters()->toArray(),
                        $this->routes,
                    ),
                ],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
        } catch (\JsonException $e) {
            throw new \RuntimeException('Cannot serialize context: ' . $e->getMessage(), 0, $e);
        }
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
    public function run(string $key = '', array $content = [], Terminal $terminal = new Terminal()): Response
    {
        return $this->current()->run($key, $content, $terminal);
    }

    /**
     * @return RouteContext<*, *>
     */
    private function current(): RouteContext
    {
        return $this->routes[$this->route] ?? $this->router->route($this->route)->start();
    }

    /**
     * @return array<mixed>
     */
    private static function decode(string $serialized): array
    {
        try {
            $context = json_decode($serialized, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException('Cannot unserialize context: ' . $e->getMessage(), 0, $e);
        }
        if (!\is_array($context)) {
            throw new \RuntimeException('Cannot unserialize context: not a JSON object');
        }

        return $context;
    }
}
