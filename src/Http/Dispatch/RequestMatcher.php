<?php

declare(strict_types=1);

namespace MiniPaviService\Http\Dispatch;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Condition of a ConditionalRequestHandler: whether its handler is for the request
 */
interface RequestMatcher
{
    public function matches(ServerRequestInterface $request): bool;
}
