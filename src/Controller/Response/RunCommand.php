<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Response;

use MiniPaviService\Controller\Flow;
use MiniPaviService\Controller\Parameters;
use MiniPaviService\Controller\Response;
use MiniPaviService\Gateway\GatewayAnswer;
use MiniPaviService\Page\InputCommand;

/**
 * Sends Videotex content and a command (e.g. reading an input field) to the gateway; what
 * the user types comes back to this controller
 */
final readonly class RunCommand extends Response
{
    /**
     * @param array<string, mixed>|Parameters $parameters
     * @param ?string $vdt Videotex content; null: none
     * @param ?InputCommand $command from Page::inputCommand(); null: only the content is sent
     */
    public function __construct(
        string|\BackedEnum $state,
        array|Parameters $parameters,
        public ?string $vdt,
        public ?InputCommand $command,
    ) {
        parent::__construct($state, $parameters);
    }

    public function continueWith(Flow $flow): GatewayAnswer
    {
        return $flow->answer($this->vdt ?? '', $this->command);
    }
}
