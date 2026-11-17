<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Response;

use MiniPaviService\Controller;
use MiniPaviService\Controller\Response;

/**
 * Switches to the controller of another route, which runs at once: it starts at STATE_INIT,
 * with the given parameters, and without the user's input (that was for the controller left).
 * The controller left is reset to STATE_INIT, without parameters.
 */
final readonly class ChangePage extends Response
{
    /**
     * @param string $route route of the target controller
     * @param array<string, mixed> $targetParameters parameters of the target controller
     */
    public function __construct(
        public string $route,
        public array $targetParameters = [],
    ) {
        parent::__construct(Controller::STATE_INIT, []);
    }
}
