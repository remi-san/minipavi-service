<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Response;

trait ResponseTrait
{
    private string $state;
    private array $parameters;

    public function init(
        string $state,
        array $parameters,
    ) {
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
