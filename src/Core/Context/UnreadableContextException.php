<?php

declare(strict_types=1);

namespace MiniPaviService\Core\Context;

use MiniPaviService\MiniPaviServiceException;

/**
 * A context that can't be read (ContextSerializer::unserialize()): not JSON, or not a JSON
 * object. A context the service didn't sign is a ForgedContextException, and one at a route
 * the router doesn't have an UnknownRouteException.
 */
final class UnreadableContextException extends \RuntimeException implements MiniPaviServiceException {}
