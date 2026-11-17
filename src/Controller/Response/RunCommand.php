<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Response;

use MiniPaviService\Controller\Flow;
use MiniPaviService\Controller\Parameters\Parameters;
use MiniPaviService\Controller\Response;
use MiniPaviService\Controller\State;
use MiniPaviService\Gateway\GatewayAnswer;
use MiniPaviService\Page\InputCommand;
use MiniPaviService\Vdt\Vdt;

/**
 * Sends Videotex content and a command (e.g. reading an input field) to the gateway; what
 * the user types comes back to this controller
 */
final readonly class RunCommand extends Response
{
    /**
     * @param Vdt $vdt Videotex content (empty: none)
     * @param ?InputCommand $command from Page::inputCommand(); null: only the content is sent
     */
    public function __construct(
        ?State $state,
        ?Parameters $parameters,
        public Vdt $vdt,
        public ?InputCommand $command,
    ) {
        parent::__construct($state, $parameters);
    }

    public function continueWith(Flow $flow): GatewayAnswer
    {
        return $flow->answer($this->vdt, $this->command);
    }
}
