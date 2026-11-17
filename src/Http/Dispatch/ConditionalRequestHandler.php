<?php

declare(strict_types=1);

namespace MiniPaviService\Http\Dispatch;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * A handler for the requests its matcher matches: a case of the Dispatcher
 */
final readonly class ConditionalRequestHandler implements RequestHandlerInterface
{
    /**
     * @param string $name of the case, in the Dispatcher's log (e.g. gateway, web)
     */
    public function __construct(
        private string $name,
        private RequestMatcher $matcher,
        private RequestHandlerInterface $handler,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    /**
     * Whether the request is for this handler
     */
    public function matches(ServerRequestInterface $request): bool
    {
        return $this->matcher->matches($request);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->handler->handle($request);
    }
}
