<?php

declare(strict_types=1);

namespace MiniPaviService\Controller;

use MiniPaviService\Controller\Parameters\Parameters;
use MiniPaviService\Gateway\CallMode;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\Gateway\InputCommand;
use MiniPaviService\Vdt\Vdt;

/**
 * What comes next in the request a response ends (Response::continueWith()): answer the
 * gateway, or run another controller, after keeping a state and parameters for the controller
 * that ran. Implemented by the runtime (Service), which owns the context: a response can't
 * change it otherwise.
 */
interface Flow
{
    /**
     * Keeps, for the controller that ran, a state and parameters for its next run; called
     * before answer() or goTo(). Without it, the controller keeps what it had.
     *
     * @param ?State $state null: its initial state
     * @param ?Parameters $parameters null: their defaults
     */
    public function keep(?State $state, ?Parameters $parameters): void;

    /**
     * Ends the request: answers the gateway with this content, and the context
     *
     * @param Vdt $vdt Videotex content
     * @param ?InputCommand $command command for the gateway (e.g. Page::inputCommand()); null: none
     * @param ?string $next URL the gateway calls next; null: this service's
     * @param ?CallMode $directCall calls next at once, without waiting for the user; null: waits
     */
    public function answer(
        Vdt $vdt,
        ?InputCommand $command = null,
        ?string $next = null,
        ?CallMode $directCall = null,
    ): GatewayResponse;

    /**
     * Runs the controller of route at once, at its initial state with parameters, and
     * without the user's input (it was for the controller left); gives the answer the request
     * ends with. The controller left is reset: its initial state, the defaults of its
     * parameters.
     *
     * @param ?Parameters $parameters parameters of the target controller; null: their defaults
     *
     * @throws \LogicException after too many page changes in one request
     */
    public function goTo(string $route, ?Parameters $parameters = null): GatewayResponse;
}
