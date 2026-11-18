<?php

declare(strict_types=1);

namespace MiniPaviService\Core;

use MiniPaviService\Controller\Flow;
use MiniPaviService\Controller\Parameters\Parameters;
use MiniPaviService\Gateway\CallMode;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\Gateway\InputCommand;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Vdt\Vdt;

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
    public function run(string $key, array $content): GatewayResponse
    {
        $this->routes[] = $this->context->route();
        $response = $this->context->run($key, $content, $this->terminal);

        // What this controller keeps for its next run
        $this->context->keep($response->state(), $response->parameters());

        return $response->continueWith($this);
    }

    public function answer(
        Vdt $vdt,
        ?InputCommand $command = null,
        ?string $next = null,
        ?CallMode $directCall = null,
    ): GatewayResponse {
        return new GatewayResponse($vdt, $this->context->serialize(), $next, $command, $directCall);
    }

    public function goTo(string $route, ?Parameters $parameters = null): GatewayResponse
    {
        if (\count($this->routes) > $this->maxPageChanges) {
            throw new \LogicException(
                'Too many page changes in one request (more than ' . $this->maxPageChanges . '): '
                    . implode(' → ', [...$this->routes, $route]),
            );
        }

        $this->context->changeRoute($route);
        $this->context->keep(null, $parameters);

        // The input was for the controller left
        return $this->run('', []);
    }
}
