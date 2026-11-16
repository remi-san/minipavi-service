<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Response;

use MiniPaviService\Context;
use MiniPaviService\Controller\Response;
use MiniPaviService\MiniPaviClient;

class ChangePage implements Response
{
    use ResponseTrait;

    public function __construct(
        string $state,
        array $parameters,
        private string $route,
    ) {
        $this->init($state, $parameters);
    }

    public function execute(MiniPaviClient $client, Context $context): void
    {
        $context->changeRoute($this->route);
    }

    public function interrupts(): bool
    {
        return false;
    }
}
