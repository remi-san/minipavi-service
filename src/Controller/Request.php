<?php

declare(strict_types=1);

namespace MiniPaviService\Controller;

use MiniPaviService\Core\FunctionKey;
use MiniPaviService\Core\Terminal;

/**
 * What a controller runs on: its state and parameters, kept in the context from the previous
 * request and typed as the controller declares them (Controller::initialState(),
 * parametersType()), and what the user did since: the key pressed and the text typed
 *
 * @template-covariant TState of State
 * @template-covariant TParameters of Parameters
 */
readonly class Request
{
    /**
     * @param TState $state
     * @param TParameters $parameters
     * @param string $key function key pressed (FunctionKey), or connection code
     *                    (GatewayRequest::FCTN_*)
     * @param list<string> $content text typed, one string per line of the input field
     */
    public function __construct(
        private State $state = ControllerState::Init,
        private Parameters $parameters = new NoParameters(),
        private string $key = '',
        private array $content = [],
        private Terminal $terminal = new Terminal(),
    ) {}

    /**
     * State the controller's previous response kept (its initial state if none)
     *
     * @return TState
     */
    public function state(): State
    {
        return $this->state;
    }

    /**
     * Parameters the controller's previous response kept (their defaults if none)
     *
     * @return TParameters
     */
    public function parameters(): Parameters
    {
        return $this->parameters;
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
