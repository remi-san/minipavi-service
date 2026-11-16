<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Response;

/**
 * Getters of Response, for the responses that have $state and $parameters properties
 */
trait ResponseTrait
{
    public function state(): string
    {
        return $this->state;
    }

    /**
     * @return array<string, mixed>
     */
    public function parameters(): array
    {
        return $this->parameters;
    }
}
