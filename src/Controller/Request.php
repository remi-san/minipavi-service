<?php

declare(strict_types=1);

namespace MiniPaviService\Controller;

use MiniPaviService\Controller;

readonly class Request
{
    public function __construct(
        private string $state = Controller::STATE_INIT,
        private array $parameters = [],
    ) {}

    public function state(): string
    {
        return $this->state;
    }

    public function parameters(): array
    {
        return $this->parameters;
    }

    public function parameter(string $name): mixed
    {
        return $this->parameters[$name] ?? null;
    }
}
