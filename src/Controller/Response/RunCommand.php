<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Response;

use MiniPaviService\Context;
use MiniPaviService\Controller\Response;
use MiniPaviService\MiniPaviClient;
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

    public function execute(MiniPaviClient $client, Context $context): void
    {
        $client->send($this->vdt ?? '', null, $context, true, $this->cmd?->toArray(), false);
    }

    public function interrupts(): bool
    {
        return true;
    }
}
