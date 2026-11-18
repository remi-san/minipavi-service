<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Response;

use MiniPaviService\Controller\Flow;
use MiniPaviService\Controller\Parameters\Parameters;
use MiniPaviService\Controller\Response;
use MiniPaviService\Gateway\GatewayResponse;

/**
 * Switches to the controller of another route, which runs at once: it starts at its initial
 * state, with the given parameters, and without the user's input (that was for the controller
 * left). The controller left is reset: its initial state, the defaults of its parameters.
 */
final readonly class ChangePage extends Response
{
    /**
     * @param string $route route of the target controller
     * @param ?Parameters $targetParameters parameters of the target controller; null: their defaults
     */
    public function __construct(
        public string $route,
        public ?Parameters $targetParameters = null,
    ) {
        parent::__construct(null, parameters: null);
    }

    public function continueWith(Flow $flow): GatewayResponse
    {
        return $flow->goTo($this->route, $this->targetParameters);
    }
}
