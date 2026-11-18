<?php

declare(strict_types=1);

namespace MiniPaviService\Core;

/**
 * A context at a route the service doesn't have (e.g. removed in a new version): what it was
 * for doesn't exist any more. Not a \RuntimeException, which means a context that can't be
 * read at all: this one is valid, but out of date.
 */
final class UnknownRouteException extends \Exception
{
    public function __construct(
        public readonly string $route,
    ) {
        parent::__construct("Unknown route \"{$route}\" in the context");
    }
}
