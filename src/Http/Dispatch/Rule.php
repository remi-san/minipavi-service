<?php

declare(strict_types=1);

namespace MiniPaviService\Http\Dispatch;

use Psr\Http\Server\RequestHandlerInterface;

/**
 * A case of the Dispatcher: the requests its matcher matches go to its handler
 */
final readonly class Rule
{
    /**
     * @param string $name of the rule, in the Dispatcher's log (e.g. gateway, web)
     */
    public function __construct(
        public string $name,
        public RequestMatcher $matcher,
        public RequestHandlerInterface $handler,
    ) {}
}
