<?php

declare(strict_types=1);

namespace MiniPaviService\Http\Dispatch;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Decides who the HTTP request is for: the first of its conditional handlers matching it,
 * or else its fallback. A service gives its own, e.g. the gateway's requests
 * (FromMiniPaviGateway) to a ServiceRunner with MiniPaviClient, browsers asking ?web=true
 * (QueryParameterEquals) to one with HtmlClient, and other browsers to the fallback
 * (EmulatorRedirect).
 */
final readonly class Dispatcher implements RequestHandlerInterface
{
    private LoggerInterface $logger;

    /**
     * @param ConditionalRequestHandlers $handlers tried in their order
     * @param RequestHandlerInterface $fallback handles the requests none of them matches
     * @param ?LoggerInterface $logger where the case handling each request is logged (debug);
     *                                 none by default
     */
    public function __construct(
        private ConditionalRequestHandlers $handlers,
        private RequestHandlerInterface $fallback,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        foreach ($this->handlers as $handler) {
            if ($handler->matches($request)) {
                $this->logger->debug('Request dispatched', ['handler' => $handler->name()]);

                return $handler->handle($request);
            }
        }

        $this->logger->debug('Request dispatched', ['handler' => 'fallback']);

        return $this->fallback->handle($request);
    }
}
