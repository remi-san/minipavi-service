<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Response;

use MiniPaviService\Controller;
use MiniPaviService\Controller\Flow;
use MiniPaviService\Controller\Parameters;
use MiniPaviService\Controller\Response;
use MiniPaviService\Gateway\GatewayAnswer;

/**
 * Switches to the controller of another route, which runs at once: it starts at STATE_INIT,
 * with the given parameters, and without the user's input (that was for the controller left).
 * The controller left is reset to STATE_INIT, without parameters.
 */
final readonly class ChangePage extends Response
{
    /** @var array<string, mixed> parameters of the target controller */
    public array $targetParameters;

    /**
     * @param string $route route of the target controller
     * @param array<string, mixed>|Parameters $targetParameters parameters of the target controller
     */
    public function __construct(
        public string $route,
        array|Parameters $targetParameters = [],
    ) {
        parent::__construct(Controller::STATE_INIT, []);
        $this->targetParameters = self::parametersArray($targetParameters);
    }

    public function continueWith(Flow $flow): GatewayAnswer
    {
        return $flow->goTo($this->route, $this->targetParameters);
    }
}
