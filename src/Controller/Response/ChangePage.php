<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Response;

use MiniPaviService\Context;
use MiniPaviService\Controller\Response;
use MiniPaviService\MiniPaviClient;

readonly class ChangePage implements Response
{
    use ResponseTrait;

    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        private string $state,
        private array $parameters,
        private string $route,
    ) {}

    public function execute(MiniPaviClient $client, Context $context): void
    {
        $context->changeRoute($this->route);
    }

    public function interrupts(): bool
    {
        return false;
    }
}
