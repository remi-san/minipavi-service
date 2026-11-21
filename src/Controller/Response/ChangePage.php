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
 * Unlike a KeepingResponse's, its parameters are the target controller's, not this one's.
 * Keeping no state, it is a response of any controller.
 *
 * @extends Response<never>
 */
final readonly class ChangePage extends Response
{
    /**
     * @param string $route route of the target controller
     * @param ?Parameters $parameters parameters of the target controller, of the class it
     *                                declares; null: their defaults
     */
    public function __construct(
        private string $route,
        private ?Parameters $parameters = null,
    ) {}

    public function continueWith(Flow $flow): GatewayResponse
    {
        return $flow->goTo($this->route, $this->parameters);
    }
}
