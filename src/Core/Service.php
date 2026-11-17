<?php

declare(strict_types=1);

namespace MiniPaviService\Core;

use MiniPaviService\Controller\Response;
use MiniPaviService\Gateway\GatewayAnswer;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Message\FrameworkMessage;
use MiniPaviService\Message\Messages;
use MiniPaviService\Message\MessagesException;
use MiniPaviService\Vdt\VdtHelper;
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
     * @param ?LoggerInterface $logger where connections, disconnections and errors are logged;
     *                                 none by default
     *
     * @throws MessagesException if the messages miss some of the framework's
     */
    public function __construct(
        private Router $router,
        Messages $messages,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->messages = $messages->check(FrameworkMessage::class);
    }

    /**
     * Answer to a request of the gateway, null for a disconnection (nothing to answer). An
     * error (exception or PHP error) is logged and answered: see error()
     */
    public function handle(GatewayRequest $request): ?GatewayAnswer
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
     * Null if even that answer fails (e.g. no default route): logged as critical.
     */
    public function error(\Throwable $error): ?GatewayAnswer
    {
        $this->logger->error('Error serving the request: ' . $error->getMessage(), ['exception' => $error]);
        try {
            // The context keeps nothing: the default controller starts at its initial state
            $flow = new RequestFlow(new Context($this->router, $this->router->defaultRoute()), new Terminal(), self::MAX_PAGE_CHANGES);

            return Response::display(VdtHelper::writeLine0($this->messages->get(FrameworkMessage::Error)))->continueWith($flow);
        } catch (\Throwable $e) {
            $this->logger->critical('Cannot answer the error: ' . $e->getMessage(), ['exception' => $e]);

            return null;
        }
    }

    private function answer(GatewayRequest $request): ?GatewayAnswer
    {
        // Nothing to answer, nor to do with the context, which may be missing
        if ($request->isDisconnecting()) {
            $this->logger->info('Disconnection');
            return null;
        }

        if ($request->isConnecting()) {
            $this->logger->info('Connection', ['fctn' => $request->key]);

            // A new session: without what was typed before
            return $this->serve(new Context($this->router, $this->router->defaultRoute()), $request->key, [], $request->terminal);
        }

        $context = $this->restore($request->context);

        return $this->serve($context, $request->key, $request->content, $request->terminal);
    }

    /**
     * Context sent with a previous answer (GatewayAnswer::$context), typed with the
     * declarations of this service's controllers (Context::unserialize())
     *
     * @throws \RuntimeException if it isn't a JSON object
     */
    public function restore(string $serialized): Context
    {
        return Context::unserialize($serialized, $this->router);
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
    public function serve(Context $context, string $key, array $content, Terminal $terminal): GatewayAnswer
    {
        // A route this service doesn't have, e.g. removed in a new version: the input was for
        // a screen that doesn't exist any more
        if (!$this->router->has($context->route())) {
            $this->logger->warning('Unknown route in the context, starting again at the default route', ['route' => $context->route()]);
            $context->restart($this->router->defaultRoute());
            $key = '';
            $content = [];
        }

        return (new RequestFlow($context, $terminal, self::MAX_PAGE_CHANGES))->run($key, $content);
    }
}
