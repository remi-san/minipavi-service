<?php

declare(strict_types=1);

namespace MiniPaviService\Core;

use MiniPaviService\InvalidValueException;
use MiniPaviService\Minitel\Terminal;

/**
 * Where the requests of the gateway run, for Service: a new session, at the router's default
 * route, or the one the context sent back by the gateway was kept for. Each request runs the
 * controllers of its session (RequestFlow) until a response responds to the gateway.
 */
final readonly class Sessions
{
    // Page changes allowed in one request, by default
    public const MAX_PAGE_CHANGES = 10;

    /**
     * @param ContextSerializer $serializer reads the context the gateway sends back, and writes
     *                                      the one each response sends, signing it (e.g. with
     *                                      HmacContextSigner); for the same router
     * @param Router $router the routes the sessions run, a new one starting at its default
     *                       route
     * @param int $maxPageChanges page changes (ChangePage) allowed in one request: more is
     *                            taken as controllers changing to each other endlessly; 0
     *                            forbids them
     *
     * @throws InvalidValueException if maxPageChanges is negative
     */
    public function __construct(
        private ContextSerializer $serializer,
        private Router $router,
        private int $maxPageChanges = self::MAX_PAGE_CHANGES,
    ) {
        if ($maxPageChanges < 0) {
            throw new InvalidValueException(
                "The page changes allowed in one request can't be negative: {$maxPageChanges}",
            );
        }
    }

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
