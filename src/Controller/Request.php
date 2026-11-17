<?php

declare(strict_types=1);

namespace MiniPaviService\Controller;

use MiniPaviService\Controller;
use MiniPaviService\Core\FunctionKey;
use MiniPaviService\Core\Terminal;

/**
 * What a controller runs on: its state and parameters, kept in the context from the previous
 * request, and what the user did since: the key pressed and the text typed
 */
readonly class Request
{
    /**
     * @param array<string, mixed> $parameters
     * @param string $key function key pressed (FunctionKey), or connection code
     *                    (GatewayRequest::FCTN_*)
     * @param list<string> $content text typed, one string per line of the input field
     */
    public function __construct(
        private string $state = Controller::STATE_INIT,
        private array $parameters = [],
        private string $key = '',
        private array $content = [],
        private Terminal $terminal = new Terminal(),
    ) {}

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

    public function parameter(string $name): mixed
    {
        return $this->parameters[$name] ?? null;
    }

    /**
     * Name of the function key pressed (FunctionKey), or connection code
     * (GatewayRequest::FCTN_*); see functionKey()
     */
    public function key(): string
    {
        return $this->key;
    }

    /**
     * Function key pressed, null for a connection code (GatewayRequest::FCTN_*)
     */
    public function functionKey(): ?FunctionKey
    {
        return FunctionKey::tryFrom($this->key);
    }

    /**
     * Text typed, one string per line of the input field
     *
     * @return list<string>
     */
    public function content(): array
    {
        return $this->content;
    }

    /**
     * Text typed, its lines put together
     */
    public function contentAsString(): string
    {
        return implode('', $this->content);
    }

    public function terminal(): Terminal
    {
        return $this->terminal;
    }
}
