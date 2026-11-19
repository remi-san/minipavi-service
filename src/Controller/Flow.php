<?php

declare(strict_types=1);

namespace MiniPaviService\Controller;

use MiniPaviService\Controller\Parameters\Parameters;
use MiniPaviService\Gateway\Command;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\Gateway\WaitForKeyCommand;
use MiniPaviService\Vdt\Vdt;

/**
 * What comes next in the request a response ends (Response::continueWith()): respond to the
 * gateway, or run another controller, after keeping a state and parameters for the controller
 * that ran. Implemented by the runtime (Service), which owns the context: a response can't
 * change it otherwise.
 */
interface Flow
{
    /**
     * Keeps, for the controller that ran, a state and parameters for its next run; called
     * before respond() or goTo(). Without it, the controller keeps what it had.
     *
     * @param ?State $state null: its initial state
     * @param ?Parameters $parameters null: their defaults
     */
    public function keep(?State $state, ?Parameters $parameters): void;

    /**
     * Ends the request: responds to the gateway with this content, and the context
     *
     * @param Vdt $vdt Videotex content
     * @param Command $command what the gateway does next (e.g. Page::inputCommand()); by
     *                         default, waits for a key
     */
    public function respond(Vdt $vdt, Command $command = new WaitForKeyCommand()): GatewayResponse;

    /**
     * Runs the controller of route at once, at its initial state with parameters, and without
     * the user's input (it was for the controller left); gives the gateway response the
     * request ends with. The controller left is reset: its initial state, the defaults of its
     * parameters.
     *
     * @param ?Parameters $parameters parameters of the target controller; null: their defaults
     *
     * @throws \LogicException after too many page changes in one request
     */
    public function goTo(string $route, ?Parameters $parameters = null): GatewayResponse;
}
