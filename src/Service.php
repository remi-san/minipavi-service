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

        if ($this->client->isConnecting()) {
            $context = new Context($this->router->defaultRoute());
            $this->client->clearContent();
            $this->logger->info('Connection', ['fctn' => $this->client->fctn()]);
        } else {
            $context = Context::unserialize($this->client->context(), $this->router->defaultRoute());
        }

        if ($this->client->isDisconnecting()) {
            $this->logger->info('Disconnection');
            return;
        }

        // What the user did, given to each controller run for this request
        $key = $this->client->fctn();
        $content = $this->client->content();
        $terminal = $this->client->terminal();

        while (true) {
            $controller = $this->router->route($context->route());
            $response = $controller->run($context->getRequest($key, $content, $terminal));

            $context->update($response);

            $response->execute($this->client, $context);

            if ($response->interrupts()) {
                return;
            }
        }
    }
}
