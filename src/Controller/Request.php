<?php

declare(strict_types=1);

namespace MiniPaviService\Controller;

use MiniPaviService\Controller\Parameters\NoParameters;
use MiniPaviService\Controller\Parameters\Parameters;
use MiniPaviService\Gateway\ConnectionCode;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\Terminal;

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
     *                    (ConnectionCode)
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
     * (ConnectionCode); see functionKey()
     */
    public function key(): string
    {
        return $this->key;
    }

    /**
     * Function key pressed, null for a connection code (ConnectionCode)
     */
    public function functionKey(): ?FunctionKey
    {
        return FunctionKey::tryFrom($this->key);
    }

    /**
     * Connection code (e.g. ConnectionCode::Connect for a new connection), null for a function
     * key
     */
    public function connectionCode(): ?ConnectionCode
    {
        return ConnectionCode::of($this->key);
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
