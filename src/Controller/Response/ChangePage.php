<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Response;

use MiniPaviService\Controller\ControllerState;
use MiniPaviService\Controller\Flow;
use MiniPaviService\Controller\Parameters;
use MiniPaviService\Controller\Response;
use MiniPaviService\Gateway\GatewayAnswer;

/**
 * Switches to the controller of another route, which runs at once: it starts at ControllerState::Init,
 * with the given parameters, and without the user's input (that was for the controller left).
 * The controller left is reset to ControllerState::Init, without parameters.
 */
final readonly class ChangePage extends Response
{
    /**
     * @param string $route route of the target controller
     * @param ?Parameters $targetParameters parameters of the target controller; null: none
     */
    public function __construct(
        public string $route,
        public ?Parameters $targetParameters = null,
    ) {
        parent::__construct(ControllerState::Init, null);
    }

    public function continueWith(Flow $flow): GatewayAnswer
    {
        return $flow->goTo($this->route, $this->targetParameters);
    }
}
