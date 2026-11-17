<?php

declare(strict_types=1);

namespace MiniPaviService;

use MiniPaviService\Controller\Parameters\Parameters;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;
use MiniPaviService\Controller\State;

/**
 * Runs on a request of the user, and returns what comes next. It declares the types of what
 * it keeps in the context, which gives them back typed in the Request: its states (an enum
 * implementing State, initialState() being one of its cases) and its parameters (a class
 * implementing Parameters, NoParameters for none). See AbstractController for one running a
 * method per ControllerState.
 *
 * initialState() and parametersType() must not use the controller's properties: the context
 * calls them for routes that don't run, and a lazy controller must stay unbuilt.
 *
 * @template TState of State
 * @template TParameters of Parameters
 */
interface Controller
{
    /**
     * State of the controller when it starts, or starts again (Response::goTo() leaving it, a
     * state its enum doesn't have): a case of the enum of its states
     *
     * @return TState
     */
    public function initialState(): State;

    /**
     * Class of its parameters
     *
     * @return class-string<TParameters>
     */
    public function parametersType(): string;

    /**
     * @param Request<TState, TParameters> $request
     */
    public function run(Request $request): Response;
}
