<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Response;

use MiniPaviService\Controller;
use MiniPaviService\Controller\Response;
use MiniPaviService\Core\Context;
use MiniPaviService\Gateway\GatewayAnswer;

/**
 * Switches to the controller of another route, which runs at once: it starts at STATE_INIT,
 * with the given parameters, and without the user's input (that was for the controller left).
 * The controller left is reset to STATE_INIT, without parameters.
 */
final readonly class ChangePage extends Response
{
    /**
     * @param array<string, mixed> $targetParameters parameters of the target controller
     */
    public function __construct(
        private string $route,
        private array $targetParameters = [],
    ) {
        parent::__construct(Controller::STATE_INIT, []);
    }

    public function execute(Context $context): ?GatewayAnswer
    {
        $context->changeRoute($this->route);
        $context->resetState();
        $context->updateParameters($this->targetParameters);

        return null;
    }
}
