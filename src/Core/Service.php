<?php

declare(strict_types=1);

namespace MiniPaviService\Core;

use MiniPaviService\Controller\Response;
use MiniPaviService\Gateway\MiniPaviClient;
use MiniPaviService\Vdt\VdtHelper;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Serves a request of the gateway: restores the context, and runs the controller of its route
 * until a response answers the gateway
 */
final class Service
{
    // Shown on line 0 when a request fails; any function key then leads to the default route
    public const ERROR_MESSAGE = 'Erreur ! Sommaire pour revenir';

    // Page changes (ChangePage) allowed in one request: more is taken as controllers changing
    // to each other endlessly
    public const MAX_PAGE_CHANGES = 10;

    private LoggerInterface $logger;

    /**
     * @param ?LoggerInterface $logger where connections, disconnections and errors are logged;
     *                                 none by default
     * @param string $errorMessage shown on line 0 when a request fails
     */
    public function __construct(
        private MiniPaviClient $client,
        private Router $router,
        ?LoggerInterface $logger = null,
        private string $errorMessage = self::ERROR_MESSAGE,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function run(): void
    {
        try {
            $this->client->start();
        } catch (\Throwable $e) {
            $this->fail($e);

            return;
        }
        $this->handle();
    }

    /**
     * Serves the request of the gateway read by MiniPaviClient::start(). An error (exception
     * or PHP error) is logged and answered: see fail()
     */
    public function handle(): void
    {
        try {
            $this->handleRequest();
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    private function handleRequest(): void
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
     * Answers a request that failed: the error message on line 0, with a new context at the
     * default route, whose controller the user's next function key runs. It is not run now:
     * if it is the one failing, the user would get the same error again at once.
     */
    private function fail(\Throwable $error): void
    {
        $this->logger->error('Error serving the request: ' . $error->getMessage(), ['exception' => $error]);
        try {
            Response::display(VdtHelper::writeLine0($this->errorMessage))
                ->execute($this->client, new Context($this->router->defaultRoute()));
        } catch (\Throwable $e) {
            $this->logger->critical('Cannot answer the error: ' . $e->getMessage(), ['exception' => $e]);
        }
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

        $routes = [$context->route()];
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

            $routes[] = $context->route();
            if (\count($routes) > self::MAX_PAGE_CHANGES + 1) {
                throw new \LogicException('Too many page changes in one request (more than ' . self::MAX_PAGE_CHANGES . '): ' . implode(' → ', $routes));
            }
        }
    }
}
