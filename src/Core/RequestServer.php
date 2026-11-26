<?php

declare(strict_types=1);

namespace MiniPaviService\Core;

use MiniPaviService\Core\Routing\UnknownRouteException;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\Minitel\ConnectionCode;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\Key;
use MiniPaviService\Minitel\KeyHandler;
use Psr\Log\LoggerInterface;

/**
 * Serves one request of the gateway, for Service, the way its key says (Key::handleWith()):
 * a function key continues the session, a connection starts a new one, a disconnection or a
 * background call gets no response
 *
 * @internal
 *
 * @implements KeyHandler<?GatewayResponse>
 */
final readonly class RequestServer implements KeyHandler
{
    public function __construct(
        private Sessions $sessions,
        private GatewayRequest $request,
        private LoggerInterface $logger,
    ) {}

    public function pressed(FunctionKey $key): GatewayResponse
    {
        return $this->continueSession($key);
    }

    public function connected(ConnectionCode $code): GatewayResponse
    {
        $this->logger->info('Connection', ['fctn' => $code->value]);

        // A new session: without what was typed before
        return $this->sessions->start($this->request->terminal)->run($code, []);
    }

    /**
     * Nothing to respond to, nor to do with the context, which may be missing
     */
    public function disconnected(): null
    {
        $this->logger->info('Disconnection');

        return null;
    }

    /**
     * Nothing to respond to: the user didn't make it
     */
    public function calledInBackground(): null
    {
        $this->logger->info('Background call, not responded to', ['fctn' => $this->request->fctn()]);

        return null;
    }

    /**
     * Runs the controller of the session's route on what the user did: the key (null for one
     * the framework doesn't know, GatewayRequest::keyOf()) and what was typed
     */
    public function continueSession(?Key $key): GatewayResponse
    {
        try {
            $flow = $this->sessions->resume($this->request->context, $this->request->terminal);
        } catch (UnknownRouteException $e) {
            // E.g. removed in a new version: the input was for a screen that doesn't exist any
            // more
            $this->logger->warning('Unknown route in the context, starting again at the default route', [
                'route' => $e->route,
            ]);

            return $this->sessions->start($this->request->terminal)->run(null, []);
        }

        return $flow->run($key, $this->request->content);
    }
}
