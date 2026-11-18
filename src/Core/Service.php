<?php

declare(strict_types=1);

namespace MiniPaviService\Core;

use MiniPaviService\Controller\Response;
use MiniPaviService\Gateway\ClientState;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\Message\FrameworkMessage;
use MiniPaviService\Message\Messages;
use MiniPaviService\Message\MessagesException;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Vdt\Vdt;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Answers a request of the gateway: restores the context, and runs the controller of its route
 * until a response answers the gateway (RequestFlow). It owns the context: it keeps the state
 * and parameters of each response, then the response says what comes next, through a Flow.
 * Without I/O: reading the request and sending the answer are the gateway client's
 * (MiniPaviClient), run by ServiceRunner.
 */
final class Service
{
    // Page changes (ChangePage) allowed in one request: more is taken as controllers changing
    // to each other endlessly
    public const MAX_PAGE_CHANGES = 10;

    private LoggerInterface $logger;

    private Messages $messages;

    /**
     * @param Messages $messages the service's, for the message of a failed request
     *                           (FrameworkMessage::Error), e.g. Messages::defaults()
     * @param ContextSerializer $serializer of the context of the answers and requests, for the
     *                                      same router, signing it (e.g. with HmacContextSigner)
     * @param ?LoggerInterface $logger where connections, disconnections and errors are logged;
     *                                 none by default
     *
     * @throws MessagesException if the messages miss some of the framework's
     */
    public function __construct(
        private Router $router,
        Messages $messages,
        private ContextSerializer $serializer,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->messages = $messages->check(FrameworkMessage::class);
    }

    /**
     * Answer to a request of the gateway, null for a disconnection or a background call
     * (nothing to answer). An
     * error (exception or PHP error) is logged and answered: see error()
     *
     * @param GatewayRequest<ClientState> $request
     */
    public function handle(GatewayRequest $request): ?GatewayResponse
    {
        try {
            return $this->answer($request);
        } catch (\Throwable $e) {
            return $this->error($e);
        }
    }

    /**
     * Answer to a request that failed, e.g. that couldn't be read: the error is logged, and
     * the error message shown on line 0, with a new context at the default route, whose
     * controller the user's next function key runs. It is not run now: if it is the one
     * failing, the user would get the same error again at once.
     *
     * Null if even that answer fails: logged as critical.
     */
    public function error(\Throwable $error): ?GatewayResponse
    {
        $this->logger->error('Error serving the request: ' . $error->getMessage(), ['exception' => $error]);
        try {
            // The context keeps nothing: the default controller starts at its initial state
            $flow = new RequestFlow(
                new Context($this->router, $this->router->defaultRoute()),
                $this->serializer,
                new Terminal(),
                self::MAX_PAGE_CHANGES,
            );

            return Response::display(Vdt::line0($this->messages->get(FrameworkMessage::Error)))->continueWith($flow);
        } catch (\Throwable $e) {
            $this->logger->critical('Cannot answer the error: ' . $e->getMessage(), ['exception' => $e]);

            return null;
        }
    }

    /**
     * @param GatewayRequest<ClientState> $request
     */
    private function answer(GatewayRequest $request): ?GatewayResponse
    {
        // Nothing to answer, nor to do with the context, which may be missing
        if ($request->isDisconnecting()) {
            $this->logger->info('Disconnection');
            return null;
        }

        if ($request->isBackgroundCall()) {
            $this->logger->info('Background call, not answered', ['fctn' => $request->fctn()]);
            return null;
        }

        if ($request->isConnecting()) {
            $this->logger->info('Connection', ['fctn' => $request->fctn()]);

            // A new session: without what was typed before
            return $this->serve(
                new Context($this->router, $this->router->defaultRoute()),
                $request->fctn(),
                [],
                $request->terminal,
            );
        }

        $context = $this->restore($request->context);

        return $this->serve($context, $request->fctn(), $request->content, $request->terminal);
    }

    /**
     * Context sent with a previous answer (GatewayResponse::$context), typed with the
     * declarations of this service's controllers (ContextSerializer::unserialize()): what
     * ServiceTester checks the route of
     *
     * @throws \RuntimeException if it isn't a JSON object
     */
    public function restore(string $serialized): Context
    {
        return $this->serializer->unserialize($serialized);
    }

    /**
     * Runs the controller of the context's route on what the user did (key, content), then
     * the controllers it changes to, until a response answers the gateway. A context whose
     * route is unknown starts again at the default route.
     *
     * @throws \LogicException after more than MAX_PAGE_CHANGES page changes
     *
     * @param list<string> $content
     */
    private function serve(Context $context, string $key, array $content, Terminal $terminal): GatewayResponse
    {
        // A route this service doesn't have, e.g. removed in a new version: the input was for
        // a screen that doesn't exist any more
        if (!$this->router->has($context->route())) {
            $this->logger->warning('Unknown route in the context, starting again at the default route', [
                'route' => $context->route(),
            ]);
            $context->restart($this->router->defaultRoute());
            $key = '';
            $content = [];
        }

        return (new RequestFlow($context, $this->serializer, $terminal, self::MAX_PAGE_CHANGES))->run($key, $content);
    }
}
