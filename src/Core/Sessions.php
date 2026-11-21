<?php

declare(strict_types=1);

namespace MiniPaviService\Core;

use MiniPaviService\Minitel\Terminal;

/**
 * Where a request of the gateway runs, for Service: a new session, or the one its context
 * (sent back by the gateway) was kept for
 *
 * @internal
 */
final readonly class Sessions
{
    /**
     * @param ContextSerializer $serializer of the context, for the router
     * @param int $maxPageChanges page changes allowed in one request (Service)
     */
    public function __construct(
        private ContextSerializer $serializer,
        private Router $router,
        private int $maxPageChanges,
    ) {}

    /**
     * Flow of a request in a new session: a new context, at the default route, keeping nothing
     */
    public function start(Terminal $terminal): RequestFlow
    {
        return $this->flow(new Context($this->router, $this->router->defaultRoute()), $terminal);
    }

    /**
     * Flow of a request in the session of its context, as the gateway sends it (signed)
     *
     * @throws UnknownRouteException for a context at a route the router doesn't have
     * @throws Signing\ForgedContextException if the signer didn't sign it
     * @throws \RuntimeException if it isn't a JSON object
     */
    public function resume(string $context, Terminal $terminal): RequestFlow
    {
        return $this->flow($this->serializer->unserialize($context), $terminal);
    }

    private function flow(Context $context, Terminal $terminal): RequestFlow
    {
        return new RequestFlow($context, $this->serializer, $terminal, $this->maxPageChanges);
    }
}
