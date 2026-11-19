<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Response;

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
     */
    public function __construct(
        ?State $state,
        ?Parameters $parameters,
        public Vdt $vdt,
    ) {
        parent::__construct($state, $parameters);
    }

    protected function next(Flow $flow): GatewayResponse
    {
        return $flow->respond($this->vdt);
    }
}
