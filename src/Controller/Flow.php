<?php

declare(strict_types=1);

namespace MiniPaviService\Controller;

use MiniPaviService\Controller\Parameters\Parameters;
use MiniPaviService\Gateway\CallMode;
use MiniPaviService\Gateway\GatewayAnswer;
use MiniPaviService\Page\InputCommand;

/**
 * What comes next in the request a response ends (Response::continueWith()): answer the
 * gateway, or run another controller. Implemented by the runtime (Service), which owns the
 * context: by the time a response gets a Flow, the state and parameters it keeps are already
 * in the context, and a response can't change it otherwise.
 */
interface Flow
{
    /**
     * Ends the request: answers the gateway with this content, and the context
     *
     * @param string $vdt Videotex content
     * @param ?InputCommand $command command for the gateway (e.g. Page::inputCommand()); null: none
     * @param ?string $next URL the gateway calls next; null: this service's
     * @param ?CallMode $directCall calls next at once, without waiting for the user; null: waits
     */
    public function answer(string $vdt, ?InputCommand $command = null, ?string $next = null, ?CallMode $directCall = null): GatewayAnswer;

    /**
     * Runs the controller of route at once, at its initial state with parameters, and
     * without the user's input (it was for the controller left); gives the answer the request
     * ends with
     *
     * @param ?Parameters $parameters parameters of the target controller; null: their defaults
     *
     * @throws \LogicException after too many page changes in one request
     */
    public function goTo(string $route, ?Parameters $parameters = null): GatewayAnswer;
}
