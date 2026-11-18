<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Response;

use MiniPaviService\Controller\Flow;
use MiniPaviService\Controller\Parameters\Parameters;
use MiniPaviService\Controller\Response;
use MiniPaviService\Controller\State;
use MiniPaviService\Gateway\GatewayResponse;

/**
 * A response that keeps a state and parameters for this controller's next run, before saying
 * what comes next (next()): every response but ChangePage, which resets the controller left
 */
abstract readonly class KeepingResponse extends Response
{
    /**
     * @param ?State $state state this controller keeps; null: its initial state
     *                      (Controller::initialState())
     * @param ?Parameters $parameters parameters this controller keeps, of the class it declares
     *                                (Controller::parametersType()); null: their defaults
     */
    public function __construct(
        private ?State $state,
        private ?Parameters $parameters,
    ) {}

    final public function continueWith(Flow $flow): GatewayResponse
    {
        $flow->keep($this->state, $this->parameters);

        return $this->next($flow);
    }

    /**
     * What comes next, once the flow keeps this response's state and parameters: answer the
     * gateway (Flow::answer()) or run another controller (Flow::goTo()); gives the answer the
     * request ends with
     */
    abstract protected function next(Flow $flow): GatewayResponse;
}
