<?php

declare(strict_types=1);

namespace MiniPaviService\Core;

/**
 * A context at a route the service doesn't have (e.g. removed in a new version): what it was
 * for doesn't exist any more
 */
final class UnknownRouteException extends \RuntimeException
{
    public function __construct(
        public readonly string $route,
    ) {
        parent::__construct("Unknown route \"{$route}\" in the context");
    }
}
