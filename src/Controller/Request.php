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
     * @param TParameters $parameters the parameters the controller's previous response kept
     *                                (their defaults if none)
     * @param ?Key $key function key pressed (FunctionKey), or connection code
     *                  (ConnectionCode); see functionKey() and connectionCode(). Null when
     *                  there is none: for the controller a ChangePage leads to, for the
     *                  default route started again because the context's route is unknown,
     *                  and for what the gateway sends that the framework doesn't know as a
     *                  key (GatewayRequest::keyOf(), e.g. DIRECT after a CallMode::Direct
     *                  call). A controller can't tell these cases apart by the key.
     * @param list<string> $content text typed, one string per line of the input field
     * @param Terminal $terminal where the user connects from, and how
     */
    public function __construct(
        public State $state,
        public Parameters $parameters = new NoParameters(),
        public ?Key $key = null,
        public array $content = [],
        public Terminal $terminal = new Terminal(),
    ) {}

    /**
     * Function key pressed, null for a connection code (ConnectionCode)
     */
    public function functionKey(): ?FunctionKey
    {
        return $this->key?->handleWith(new FunctionKeyReader());
    }

    /**
     * Connection code (e.g. ConnectionCode::Connect for a new connection), null for a function
     * key
     */
    public function connectionCode(): ?ConnectionCode
    {
        return $this->key?->handleWith(new ConnectionCodeReader());
    }

    /**
     * Text typed, its lines put together
     */
    public function contentAsString(): string
    {
        return implode('', $this->content);
    }
}
