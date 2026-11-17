<?php

declare(strict_types=1);

namespace MiniPaviService\Http\Dispatch;

use Psr\Http\Message\ServerRequestInterface;

/**
 * A request whose query has a parameter of exactly this value (e.g. web=true: not web=1, nor
 * web=TRUE)
 */
final readonly class QueryParameterEquals implements RequestMatcher
{
    public function __construct(
        private string $name,
        private string $value,
    ) {}

    public function matches(ServerRequestInterface $request): bool
    {
        return ($request->getQueryParams()[$this->name] ?? null) === $this->value;
    }
}
