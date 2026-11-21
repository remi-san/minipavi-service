<?php

declare(strict_types=1);

namespace MiniPaviService\Core;

use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\Message\FrameworkMessage;
use MiniPaviService\Message\Messages;
use MiniPaviService\Message\MessagesException;
use MiniPaviService\Message\MessagesXmlParser;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Vdt\Vdt;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Responds to a request of the gateway: restores the context, and runs the controller of its
 * route until a response responds to the gateway (RequestFlow). It owns the context: it keeps
 * the state and parameters of each response, then the response says what comes next, through a
 * Flow. Without I/O: reading the request and sending the response are the gateway client's
 * (MiniPaviClient), run by ServiceRunner.
 */
final class Service
{
    private LoggerInterface $logger;

    private Messages $messages;

    /**
     * @param Sessions $sessions where the requests run: a new session, or the one of their
     *                           context
     * @param Messages $messages the service's, for the message of a failed request
     *                           (FrameworkMessage::Error), e.g. MessagesXmlParser::defaults()
     * @param ?LoggerInterface $logger where connections, disconnections and errors are logged;
     *                                 none by default
     *
     * @throws MessagesException if the messages miss some of the framework's
     */
    public function __construct(
        private Sessions $sessions,
        Messages $messages,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->messages = $messages->check(...FrameworkMessage::cases());
    }

    /**
     * Response to a request of the gateway, null for a disconnection or a background call
     * (nothing to respond to). An error (exception or PHP error) is logged and responded to:
     * see error()
     */
    public function handle(GatewayRequest $request): ?GatewayResponse
    {
        try {
            return $this->respond($request);
        } catch (\Throwable $e) {
            return $this->error($e);
        }
    }

    /**
     * Response to a request that couldn't be read (by the client, ServiceRunner), as to one
     * that failed: see error()
     */
    public function unreadable(\Throwable $error): ?GatewayResponse
    {
        return $this->error($error);
    }

    /**
     * Response to a request that failed: the error is logged, and the error message shown on
     * line 0, with a new context at the default route, whose controller the user's next
     * function key runs. It is not run now: if it is the one failing, the user would get the
     * same error again at once.
     *
     * Null if even that response fails: logged as critical.
     */
    private function error(\Throwable $error): ?GatewayResponse
    {
        $this->logger->error('Error serving the request', ['exception' => $error]);
        try {
            // The context keeps nothing: the default controller starts at its initial state
            return $this->sessions
                ->start(new Terminal())
                ->respond(Vdt::line0($this->messages->get(FrameworkMessage::Error)));
        } catch (\Throwable $e) {
            $this->logger->critical('Cannot respond to the error', ['exception' => $e]);

            return null;
        }
    }

    /**
     * Response the request's key says (RequestServer); a key the framework doesn't know
     * (null) continues the session, as a function key does
     */
    private function respond(GatewayRequest $request): ?GatewayResponse
    {
        $server = new RequestServer($this->sessions, $request, $this->logger);

        return $request->key === null ? $server->continueSession(null) : $request->key->handleWith($server);
    }
}
