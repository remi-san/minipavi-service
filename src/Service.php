<?php

namespace MiniPaviService;

final class Service
{
    public function __construct(
        private MiniPaviClient $client,
        private Router $router,
    ) {}

    public function run(): void
    {

        $this->client->start();

        if ($this->client->isConnecting()) {
            $context = new Context($this->router->defaultRoute());
            $this->client->clearContent();
            trigger_error("[MiniPaviService] CNX");
        } else {
            $context = Context::unserialize($this->client->context());
        }

        if ($this->client->isDisconnecting()) {
            trigger_error("[MiniPaviService] DECO");
            return;
        }

        while (true) {
            $controller = $this->router->route($context->route());
            $response = $controller->run($context->getRequest());

            $context->update($response);

            $response->execute($this->client, $context);

            if ($response->interrupts()) {
                return;
            }
        }
    }
}
