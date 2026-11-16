<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Response;

use MiniPaviService\Context;
use MiniPaviService\Controller\Response;
use MiniPaviService\MiniPaviClient;

readonly class DisplayPage implements Response
{
    use ResponseTrait;

    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        private string $state,
        private array $parameters,
        public string $vdt,
    ) {}

    public function execute(MiniPaviClient $client, Context $context): void
    {
        $client->send($this->vdt, null, $context, true, null, false);
    }

    public function interrupts(): bool
    {
        return true;
    }
}
