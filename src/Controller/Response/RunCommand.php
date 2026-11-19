<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Response;

use MiniPaviService\Controller\Flow;
use MiniPaviService\Controller\Parameters\Parameters;
use MiniPaviService\Controller\State;
use MiniPaviService\Gateway\Command;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\Vdt\Vdt;

/**
 * Sends Videotex content and a command (e.g. reading an input field) to the gateway; what
 * the user types comes back to this controller
 */
final readonly class RunCommand extends KeepingResponse
{
    /**
     * @param Vdt $vdt Videotex content (empty: none)
     * @param Command $command e.g. Page::inputCommand(); WaitForKeyCommand: only the content is
     *                         sent
     */
    public function __construct(
        ?State $state,
        ?Parameters $parameters,
        public Vdt $vdt,
        public Command $command,
    ) {
        parent::__construct($state, $parameters);
    }

    protected function next(Flow $flow): GatewayResponse
    {
        return $flow->answer($this->vdt, $this->command);
    }
}
