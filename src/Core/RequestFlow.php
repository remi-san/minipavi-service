<?php

declare(strict_types=1);

namespace MiniPaviService\Core;

use MiniPaviService\Controller\CallMode;
use MiniPaviService\Controller\Flow;
use MiniPaviService\Gateway\GatewayAnswer;
use MiniPaviService\Page\InputCommand;

/**
 * Flow of one request of the gateway, for Service: runs the controller of the context's
 * route, keeps the state and parameters of its response, and lets the response say what
 * comes next. Owns every change to the context, in this order.
 *
 * @internal
 */
final class RequestFlow implements Flow
{
    /** @var list<string> routes run in this request, in order */
    private array $routes = [];

    public function __construct(
        private Router $router,
        private Context $context,
        private Terminal $terminal,
        private int $maxPageChanges,
    ) {}

    /**
     * Runs the controller of the context's route on what the user did (key, content), and
     * gives the answer the request ends with
     *
     * @param list<string> $content
     */
    public function run(string $key, array $content): GatewayAnswer
    {
        $this->routes[] = $this->context->route();
        $controller = $this->router->route($this->context->route());
        $response = $controller->run($this->context->getRequest($key, $content, $this->terminal));

        // What this controller keeps for its next run
        $this->context->updateState($response->state());
        $this->context->updateParameters($response->parameters());

        return $response->continueWith($this);
    }

    public function answer(string $vdt, ?InputCommand $command = null, ?string $next = null, ?CallMode $directCall = null): GatewayAnswer
    {
        return new GatewayAnswer($vdt, $this->context->serialize(), $next, $command?->toArray(), $directCall);
    }

    public function goTo(string $route, array $parameters = []): GatewayAnswer
    {
        if (\count($this->routes) > $this->maxPageChanges) {
            throw new \LogicException('Too many page changes in one request (more than ' . $this->maxPageChanges . '): ' . implode(' → ', [...$this->routes, $route]));
        }

        $this->context->changeRoute($route);
        $this->context->resetState();
        $this->context->updateParameters($parameters);

        // The input was for the controller left
        return $this->run('', []);
    }
}
