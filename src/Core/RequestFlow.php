<?php

declare(strict_types=1);

namespace MiniPaviService\Core;

use MiniPaviService\Controller\Flow;
use MiniPaviService\Controller\Parameters\Parameters;
use MiniPaviService\Controller\State;
use MiniPaviService\Core\Context\Context;
use MiniPaviService\Core\Context\ContextSerializer;
use MiniPaviService\Gateway\Command\Command;
use MiniPaviService\Gateway\Command\WaitForKeyCommand;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\Minitel\Key;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Vdt\Vdt;

/**
 * Flow of one request of the gateway, for Service: runs the controller of the context's
 * route, keeps the state and parameters of its response, and lets the response say what
 * comes next. Owns every change to the context, in this order.
 *
 * @internal
 *
 * @implements Flow<State>
 */
final class RequestFlow implements Flow
{
    /** @var list<string> routes run in this request, in order */
    private array $routes = [];

    public function __construct(
        private Context $context,
        private ContextSerializer $serializer,
        private Terminal $terminal,
        private int $maxPageChanges,
    ) {}

    /**
     * Runs the controller of the context's route on what the user did (key, content), and
     * gives the gateway response the request ends with
     *
     * @param list<string> $content
     */
    public function run(?Key $key, array $content): GatewayResponse
    {
        $this->routes[] = $this->context->route();
        $response = $this->context->run($key, $content, $this->terminal);

        return $response->continueWith($this);
    }

    public function keep(?State $state, ?Parameters $parameters): void
    {
        $this->context->keep($state, $parameters);
    }

    public function respond(Vdt $vdt, Command $command = new WaitForKeyCommand()): GatewayResponse
    {
        return new GatewayResponse($vdt, $this->serializer->serialize($this->context), $command);
    }

    public function goTo(string $route, ?Parameters $parameters = null): GatewayResponse
    {
        if (\count($this->routes) > $this->maxPageChanges) {
            throw new \LogicException(
                'Too many page changes in one request (more than ' . $this->maxPageChanges . '): '
                    . implode(' → ', [...$this->routes, $route]),
            );
        }

        $this->context->leaveFor($route, $parameters);

        // The input was for the controller left
        return $this->run(null, []);
    }
}
