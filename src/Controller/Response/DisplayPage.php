<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Response;

use MiniPaviService\Controller\ControllerState;
use MiniPaviService\Controller\Flow;
use MiniPaviService\Controller\Parameters\Parameters;
use MiniPaviService\Controller\State;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\Vdt\Vdt;

/**
 * Sends Videotex content to the gateway; the next key comes back to this controller
 */
final readonly class DisplayPage extends KeepingResponse
{
    /**
     * @param Vdt $vdt Videotex content
     * @param ?State $state state this controller keeps (null: its initial state); by default,
     *                      it reads the next input
     * @param ?Parameters $parameters parameters this controller keeps; null: their defaults
     */
    public function __construct(
        public Vdt $vdt,
        ?State $state = ControllerState::ReadInput,
        ?Parameters $parameters = null,
    ) {
        parent::__construct($state, $parameters);
    }

    protected function next(Flow $flow): GatewayResponse
    {
        return $flow->respond($this->vdt);
    }
}
