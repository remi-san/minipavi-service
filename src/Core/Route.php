<?php

declare(strict_types=1);

namespace MiniPaviService\Core;

use MiniPaviService\Controller;
use MiniPaviService\Controller\Parameters\Parameters;
use MiniPaviService\Controller\Parameters\Values;
use MiniPaviService\Controller\State;

/**
 * A controller of the Router, with the types of what it keeps (Controller::initialState(),
 * parametersType()) bound to it: it makes the RouteContext keeping them typed for a request.
 *
 * @template-covariant TState of State
 * @template-covariant TParameters of Parameters
 */
final readonly class Route
{
    /**
     * @param Controller<TState, TParameters> $controller
     */
    public function __construct(
        private Controller $controller,
    ) {}

    /**
     * Context of the route as it starts: the initial state, the defaults of the parameters
     *
     * @return RouteContext<TState, TParameters>
     */
    public function start(): RouteContext
    {
        return $this->restore(null, new Values());
    }

    /**
     * Context of the route as the context kept it: the state of the enum whose value is state
     * (the initial one if there is none, or for null), the parameters read from values
     * (Parameters::fromValues())
     *
     * @return RouteContext<TState, TParameters>
     */
    public function restore(?string $state, Values $values): RouteContext
    {
        $type = $this->controller->parametersType();

        return new RouteContext($this->controller, $this->stateOf($state), $type::fromValues($values));
    }

    /**
     * @return TState
     */
    private function stateOf(?string $value): State
    {
        $initialState = $this->controller->initialState();
        foreach ($initialState::cases() as $case) {
            if ((string) $case->value === $value) {
                return $case;
            }
        }

        return $initialState;
    }
}
