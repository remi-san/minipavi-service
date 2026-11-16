<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Response;

trait ResponseTrait
{
    private readonly string $state;
    private readonly array $parameters;

    public function init(
        string $state,
        array $parameters,
    ): void {
        $this->state = $state;
        $this->parameters = $parameters;
    }

    public function state(): string
    {
        return $this->state;
    }

    public function parameters(): array
    {
        return $this->parameters;
    }
}
