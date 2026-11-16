<?php

declare(strict_types=1);

namespace MiniPaviService\Controller;

use MiniPaviService\Context;
use MiniPaviService\MiniPaviClient;

/**
 * What a controller returns: what to do now (execute()), and the state and parameters it
 * keeps for its next run, in the context
 */
abstract readonly class Response
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        private string $state,
        private array $parameters,
    ) {}

    abstract public function execute(MiniPaviClient $client, Context $context): void;

    /**
     * Whether the response answers the gateway, ending the request
     */
    abstract public function interrupts(): bool;

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
