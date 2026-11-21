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
 *
 * @template-covariant TState of State = ControllerState::ReadInput
 *
 * @extends KeepingResponse<TState>
 */
final readonly class DisplayPage extends KeepingResponse
{
    /**
     * @param Vdt $vdt Videotex content
     * @param ?TState $state state this controller keeps (null: its initial state); by default,
     *                      it reads the next input (ControllerState::ReadInput: a
     *                      controller with its own State enum gives one of its states;
     *                      static analysis types null as the default too, so it gives
     *                      its initialState() rather than null)
     * @param ?Parameters $parameters parameters this controller keeps; null: their defaults
     */
    public function __construct(
        private Vdt $vdt,
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
