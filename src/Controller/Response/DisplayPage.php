<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Response;

use MiniPaviService\Context;
use MiniPaviService\Controller\Response;
use MiniPaviService\MiniPaviClient;

final readonly class DisplayPage extends Response
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        string $state,
        array $parameters,
        public string $vdt,
    ) {
        parent::__construct($state, $parameters);
    }

    public function execute(MiniPaviClient $client, Context $context): void
    {
        $client->send($this->vdt, null, $context, true, null, false);
    }

    public function interrupts(): bool
    {
        return true;
    }
}
