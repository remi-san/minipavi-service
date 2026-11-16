<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Response;

use MiniPaviService\Context;
use MiniPaviService\Controller\Response;
use MiniPaviService\MiniPaviClient;

readonly class RunCommand implements Response
{
    use ResponseTrait;

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $cmd MiniPavi command, e.g. from Page::readInput()
     */
    public function __construct(
        private string $state,
        private array $parameters,
        private ?string $vdt,
        private array $cmd,
    ) {}

    public function execute(MiniPaviClient $client, Context $context): void
    {
        $client->send($this->vdt ?? '', null, $context, true, $this->cmd, false);
    }

    public function interrupts(): bool
    {
        return true;
    }
}
