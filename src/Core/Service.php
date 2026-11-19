<?php

declare(strict_types=1);

namespace MiniPaviService\Core;

use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\InvalidValueException;
use MiniPaviService\Message\FrameworkMessage;
use MiniPaviService\Message\Messages;
use MiniPaviService\Message\MessagesException;
use MiniPaviService\Message\MessagesXmlParser;
use MiniPaviService\Minitel\Key;
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
    // Page changes allowed in one request, by default
    public const MAX_PAGE_CHANGES = 10;

    private LoggerInterface $logger;

    private Messages $messages;

    /**
     * @param Messages $messages the service's, for the message of a failed request
     *                           (FrameworkMessage::Error), e.g. MessagesXmlParser::defaults()
     * @param ContextSerializer $serializer of the context of the responses and requests, for
     *                                      the same router, signing it (e.g. with
     *                                      HmacContextSigner)
     * @param int $maxPageChanges page changes (ChangePage) allowed in one request:
     *                            more is taken as controllers changing to each other
     *                            endlessly; 0 forbids them
     * @param ?LoggerInterface $logger where connections, disconnections and errors are logged;
     *                                 none by default
     *
     * @throws MessagesException if the messages miss some of the framework's
     * @throws InvalidValueException if maxPageChanges is negative
     */
    public function __construct(
        private Router $router,
        Messages $messages,
        private ContextSerializer $serializer,
        private int $maxPageChanges = self::MAX_PAGE_CHANGES,
        ?LoggerInterface $logger = null,
    ) {
        if ($maxPageChanges < 0) {
            throw new InvalidValueException(
                "The page changes allowed in one request can't be negative: {$maxPageChanges}",
            );
        }
        $this->logger = $logger ?? new NullLogger();
        $this->messages = $messages->check(FrameworkMessage::class);
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
            return $this->flow($this->newContext(), new Terminal())->respond(Vdt::line0($this->messages->get(
                FrameworkMessage::Error,
            )));
        } catch (\Throwable $e) {
            $this->logger->critical('Cannot respond to the error', ['exception' => $e]);

            return null;
        }
    }

    private function respond(GatewayRequest $request): ?GatewayResponse
    {
        // Nothing to respond to, nor to do with the context, which may be missing
        if ($request->isDisconnecting()) {
            $this->logger->info('Disconnection');
            return null;
        }

        if ($request->isBackgroundCall()) {
            $this->logger->info('Background call, not responded to', ['fctn' => $request->fctn()]);
            return null;
        }

        if ($request->isConnecting()) {
            $this->logger->info('Connection', ['fctn' => $request->fctn()]);

            // A new session: without what was typed before
            return $this->serve($this->newContext(), $request->key, [], $request->terminal);
        }

        try {
            $context = $this->serializer->unserialize($request->context);
        } catch (UnknownRouteException $e) {
            // E.g. removed in a new version: the input was for a screen that doesn't exist any
            // more
            $this->logger->warning('Unknown route in the context, starting again at the default route', [
                'route' => $e->route,
            ]);

            return $this->serve($this->newContext(), key: null, content: [], terminal: $request->terminal);
        }

        return $this->serve($context, $request->key, $request->content, $request->terminal);
    }

    /**
     * A new context, at the default route, keeping nothing: as for a new connection
     */
    private function newContext(): Context
    {
        return new Context($this->router, $this->router->defaultRoute());
    }

    /**
     * Runs the controller of the context's route on what the user did (key, content), then
     * the controllers it changes to, until a response responds to the gateway
     *
     * @throws \LogicException after more than maxPageChanges page changes
     *
     * @param list<string> $content
     */
    private function serve(Context $context, ?Key $key, array $content, Terminal $terminal): GatewayResponse
    {
        return $this->flow($context, $terminal)->run($key, $content);
    }

    /**
     * Flow of a request on the context, from the terminal
     */
    private function flow(Context $context, Terminal $terminal): RequestFlow
    {
        return new RequestFlow($context, $this->serializer, $terminal, $this->maxPageChanges);
    }
}
