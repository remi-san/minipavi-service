<?php

declare(strict_types=1);

namespace MiniPaviService;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Serves a request of the gateway: restores the context, and runs the controller of its route
 * until a response answers the gateway
 */
final class Service
{
    private LoggerInterface $logger;

    /**
     * @param ?LoggerInterface $logger where connections and disconnections are logged; none by
     *                                 default
     */
    public function __construct(
        private MiniPaviClient $client,
        private Router $router,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function run(): void
    {
        $this->client->start();
        $this->handle();
    }

    /**
     * Serves the request of the gateway read by MiniPaviClient::start()
     */
    public function handle(): void
    {
        // Nothing to answer, nor to do with the context, which may be missing
        if ($this->client->isDisconnecting()) {
            $this->logger->info('Disconnection');
            return;
        }

        if ($this->client->isConnecting()) {
            $context = new Context($this->router->defaultRoute());
            $this->client->clearContent();
            $this->logger->info('Connection', ['fctn' => $this->client->fctn()]);
        } else {
            $context = Context::unserialize($this->client->context(), $this->router->defaultRoute());
        }

        $this->serve($context, $this->client->fctn(), $this->client->content(), $this->client->terminal());
    }

    /**
     * Runs the controller of the context's route on what the user did (key, content), then
     * the controllers it changes to, until a response answers the gateway. A context whose
     * route is unknown starts again at the default route.
     *
     * @param list<string> $content
     */
    public function serve(Context $context, string $key, array $content, Terminal $terminal): void
    {
        // A route this service doesn't have, e.g. removed in a new version: the input was for
        // a screen that doesn't exist any more
        if (!$this->router->has($context->route())) {
            $this->logger->warning('Unknown route in the context, starting again at the default route', ['route' => $context->route()]);
            $context->restart($this->router->defaultRoute());
            $key = '';
            $content = [];
        }

        while (true) {
            $controller = $this->router->route($context->route());
            $response = $controller->run($context->getRequest($key, $content, $terminal));

            $context->update($response);

            $response->execute($this->client, $context);

            if ($response->interrupts()) {
                return;
            }
            // The next controller didn't get the input: it was for this one
            $key = '';
            $content = [];
        }
    }
}
