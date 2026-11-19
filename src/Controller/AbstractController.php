<?php

declare(strict_types=1);

namespace MiniPaviService\Controller;

use MiniPaviService\Controller;
use MiniPaviService\Controller\Parameters\Parameters;
use MiniPaviService\Controller\Parameters\ParametersType;

/**
 * Controller running the method of its state (ControllerState): onDisplay() to show its
 * screen, and onReadInput() to handle what the user typed or pressed, the two to write.
 * onInit() and onInitInput() show the screen too, unless overridden.
 *
 * A state the controller doesn't know (e.g. kept in the context by an older version of the
 * service) starts it again, as ControllerState::Init. It declares the type of its
 * parameters (parametersType(): ParametersType::none() for none), and names their class in
 * its extends tag (AbstractController<TheClass>). A
 * controller with states of its own implements Controller, with its own enum.
 *
 * @template TParameters of Parameters
 *
 * @implements Controller<ControllerState, TParameters>
 */
abstract class AbstractController implements Controller
{
    final public function initialState(): ControllerState
    {
        return ControllerState::Init;
    }

    /**
     * Type of its parameters: ParametersType::none() for none, or e.g.
     * ParametersType::of(PageParameters::class). Abstract, so that a controller declaring a
     * type of parameters (@extends) can't inherit another one
     *
     * @return ParametersType<TParameters>
     */
    abstract public function parametersType(): ParametersType;

    public function run(Request $request): Response
    {
        return match ($request->state()) {
            ControllerState::Init => $this->onInit($request),
            ControllerState::Display => $this->onDisplay($request),
            ControllerState::InitInput => $this->onInitInput($request),
            ControllerState::ReadInput => $this->onReadInput($request),
        };
    }

    /**
     * First run, or the user comes back to the controller: shows the screen
     *
     * @param Request<ControllerState, TParameters> $request
     *
     * @return Response<ControllerState>
     */
    protected function onInit(Request $request): Response
    {
        return $this->onDisplay($request);
    }

    /**
     * Shows the screen, usually with RunCommand::readInput() to read what the user types
     *
     * @param Request<ControllerState, TParameters> $request
     *
     * @return Response<ControllerState>
     */
    abstract protected function onDisplay(Request $request): Response;

    /**
     * Asks for input again: shows the screen again
     *
     * @param Request<ControllerState, TParameters> $request
     *
     * @return Response<ControllerState>
     */
    protected function onInitInput(Request $request): Response
    {
        return $this->onDisplay($request);
    }

    /**
     * Handles the key pressed (Request::key()) and the text typed (Request::content())
     *
     * @param Request<ControllerState, TParameters> $request
     *
     * @return Response<ControllerState>
     */
    abstract protected function onReadInput(Request $request): Response;
}
