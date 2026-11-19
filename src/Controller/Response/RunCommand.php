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
 * Sends Videotex content and a command to the gateway: reading an input field
 * (Response::readInput()), what the user types coming back to this controller; or calling
 * another service (Response::connectTo()), this controller's state and parameters kept for
 * when the user comes back
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
