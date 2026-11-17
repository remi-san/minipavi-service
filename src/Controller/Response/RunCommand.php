<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Response;

use MiniPaviService\Controller\Response;
use MiniPaviService\Core\Context;
use MiniPaviService\Gateway\GatewayAnswer;
use MiniPaviService\Page\InputCommand;

final readonly class RunCommand extends Response
{
    /**
     * @param array<string, mixed> $parameters
     * @param ?InputCommand $cmd from Page::inputCommand(); null: only the content is sent
     */
    public function __construct(
        string $state,
        array $parameters,
        private ?string $vdt,
        private ?InputCommand $cmd,
    ) {
        parent::__construct($state, $parameters);
    }

    public function execute(Context $context): GatewayAnswer
    {
        return new GatewayAnswer($this->vdt ?? '', $context->serialize(), command: $this->cmd?->toArray());
    }
}
