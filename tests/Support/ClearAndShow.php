<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Controller\ControllerState;
use MiniPaviService\Controller\Flow;
use MiniPaviService\Controller\Response;
use MiniPaviService\Gateway\GatewayAnswer;
use MiniPaviService\Vdt\VdtHelper;

/**
 * A response the framework doesn't have: clears the screen, then shows vdt
 */
final readonly class ClearAndShow extends Response
{
    public function __construct(private string $vdt)
    {
        parent::__construct(ControllerState::Display, new PageParameters(1));
    }

    public function continueWith(Flow $flow): GatewayAnswer
    {
        return $flow->answer(VdtHelper::clearScreen() . $this->vdt);
    }
}
