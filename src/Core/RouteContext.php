<?php

declare(strict_types=1);

namespace MiniPaviService\Core;

use MiniPaviService\Controller;
use MiniPaviService\Controller\Parameters\Parameters;
use MiniPaviService\Controller\Parameters\Values;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;
use MiniPaviService\Controller\State;
use MiniPaviService\Minitel\Key;
use MiniPaviService\Minitel\Terminal;

/**
 * State and parameters a route keeps in the context, typed as its controller declares them,
 * and the controller to run on them (Route::start(), restore())
 *
 * @template-covariant TState of State
 * @template-covariant TParameters of Parameters
 */
final class RouteContext
{
    /**
     * @param Controller<TState, TParameters> $controller
     * @param TState $state
     * @param TParameters $parameters
     */
    public function __construct(
        private readonly Controller $controller,
        private State $state,
        private Parameters $parameters,
    ) {}

    /**
     * @return TState
     */
    public function state(): State
    {
        return $this->state;
    }

    /**
     * @return TParameters
     */
    public function parameters(): Parameters
    {
        return $this->parameters;
    }

    /**
     * Keeps a state and parameters, e.g. a Response's
     *
     * @param ?State $state a case of the enum of the controller's states; null: the initial one
     * @param ?Parameters $parameters of the class the controller declares; null: the defaults
     *
     * @throws \LogicException for a state or parameters of other types than the controller's
     */
    public function keep(?State $state, ?Parameters $parameters): void
    {
        $initialState = $this->controller->initialState();
        $type = $this->controller->parametersType();

        if ($state !== null && !$state instanceof $initialState) {
            throw new \LogicException(\sprintf(
                '%s keeps states of %s, not %s',
                $this->controller::class,
                $initialState::class,
                $state::class,
            ));
        }
        if ($parameters !== null && !$parameters instanceof $type) {
            throw new \LogicException(\sprintf(
                '%s keeps parameters of %s, not %s',
                $this->controller::class,
                $type,
                $parameters::class,
            ));
        }

        $this->state = $state ?? $initialState;
        $this->parameters = $parameters ?? $type::fromValues(new Values());
    }

    /**
     * Runs the controller on its state and parameters, and what the user did
     *
     * @param list<string> $content
     */
    public function run(?Key $key, array $content, Terminal $terminal): Response
    {
        return $this->controller->run(new Request($this->state, $this->parameters, $key, $content, $terminal));
    }
}
