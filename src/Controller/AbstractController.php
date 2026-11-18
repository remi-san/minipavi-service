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
 * service) starts it again, as ControllerState::Init. It has no parameters, unless it
 * overrides parametersType() (e.g. ParametersType::of(PageParameters::class)), with an
 * extends tag naming their class (AbstractController<TheClass>). A
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
     * No parameters, unless overridden (e.g. ParametersType::of(PageParameters::class))
     *
     * @return ParametersType<TParameters>
     */
    public function parametersType(): ParametersType
    {
        /** @var ParametersType<TParameters> */
        return ParametersType::none();
    }

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
     */
    protected function onInit(Request $request): Response
    {
        return $this->onDisplay($request);
    }

    /**
     * Shows the screen, usually with Response::readInput() to read what the user types
     *
     * @param Request<ControllerState, TParameters> $request
     */
    abstract protected function onDisplay(Request $request): Response;

    /**
     * Asks for input again: shows the screen again
     *
     * @param Request<ControllerState, TParameters> $request
     */
    protected function onInitInput(Request $request): Response
    {
        return $this->onDisplay($request);
    }

    /**
     * Handles the key pressed (Request::key()) and the text typed (Request::content())
     *
     * @param Request<ControllerState, TParameters> $request
     */
    abstract protected function onReadInput(Request $request): Response;
}
