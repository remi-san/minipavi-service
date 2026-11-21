<?php

declare(strict_types=1);

namespace MiniPaviService\Controller;

use MiniPaviService\Controller\Parameters\NoParameters;
use MiniPaviService\Controller\Parameters\Parameters;
use MiniPaviService\Minitel\ConnectionCode;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\Key;
use MiniPaviService\Minitel\Terminal;

/**
 * What a controller runs on: its state and parameters, kept in the context from the previous
 * request and typed as the controller declares them (Controller::initialState(),
 * parametersType()), and what the user did since: the key pressed and the text typed
 *
 * @template-covariant TState of State
 * @template-covariant TParameters of Parameters
 */
final readonly class Request
{
    /**
     * @param TState $state the state the controller's previous response kept, or its
     *                      initial state (Controller::initialState()): a state of that
     *                      controller, so there is no default
     * @param TParameters $parameters
     * @param ?Key $key function key pressed (FunctionKey), or connection code
     *                  (ConnectionCode); null for none (see key())
     * @param list<string> $content text typed, one string per line of the input field
     */
    public function __construct(
        private State $state,
        private Parameters $parameters = new NoParameters(),
        private ?Key $key = null,
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
     * Function key pressed (FunctionKey), or connection code (ConnectionCode). See
     * functionKey() and connectionCode().
     *
     * Null when there is none: for the controller a ChangePage leads to, for the default
     * route started again because the context's route is unknown, and for what the gateway
     * sends that the framework doesn't know as a key (GatewayRequest::keyOf(), e.g. DIRECT
     * after a CallMode::Direct call). A controller can't tell these cases apart by the key.
     */
    public function key(): ?Key
    {
        return $this->key;
    }

    /**
     * Function key pressed, null for a connection code (ConnectionCode)
     */
    public function functionKey(): ?FunctionKey
    {
        return $this->key?->functionKey();
    }

    /**
     * Connection code (e.g. ConnectionCode::Connect for a new connection), null for a function
     * key
     */
    public function connectionCode(): ?ConnectionCode
    {
        return $this->key?->connectionCode();
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
