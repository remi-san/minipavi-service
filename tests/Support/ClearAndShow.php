<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Controller\ControllerState;
use MiniPaviService\Controller\Flow;
use MiniPaviService\Controller\Pagination\PageParameters;
use MiniPaviService\Controller\Response\KeepingResponse;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\Vdt\Vdt;

/**
 * A response the framework doesn't have: clears the screen, then shows vdt
 *
 * @extends KeepingResponse<ControllerState>
 */
final readonly class ClearAndShow extends KeepingResponse
{
    public function __construct(
        private string $vdt,
    ) {
        parent::__construct(ControllerState::Display, new PageParameters(1));
    }

    protected function next(Flow $flow): GatewayResponse
    {
        return $flow->respond(Vdt::clearScreen()->append(Vdt::text($this->vdt)));
    }
}
