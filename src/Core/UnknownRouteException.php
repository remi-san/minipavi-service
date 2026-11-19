<?php

declare(strict_types=1);

namespace MiniPaviService\Core;

/**
 * A route the router doesn't have (Router::route()): a context at such a route (e.g. removed in
 * a new version), which Service starts again at the default route, or a controller going to it
 * (Response::goTo()), an error of the service. Not a \RuntimeException, which means a context
 * that can't be read at all: such a context is valid, but out of date.
 */
final class UnknownRouteException extends \Exception
{
    /**
     * @param list<string> $routes the routes the router has
     */
    public function __construct(
        public readonly string $route,
        array $routes,
    ) {
        parent::__construct("Unknown route \"{$route}\" (routes: " . implode(', ', $routes) . ')');
    }
}
