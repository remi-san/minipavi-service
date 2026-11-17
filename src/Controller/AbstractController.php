<?php

declare(strict_types=1);

namespace MiniPaviService\Controller;

use MiniPaviService\Controller;

/**
 * Controller running the method of its state (ControllerState): onDisplay() to show its screen, and
 * onReadInput() to handle what the user typed or pressed, the two to write. onInit() and
 * onInitInput() show the screen too, unless overridden.
 *
 * A state the controller doesn't know (e.g. kept in the context by an older version of the
 * service) starts it again, as ControllerState::Init. To add states, name them in an enum
 * implementing State, override run() to read it (Request::stateAs()), and call parent::run()
 * for the others.
 */
abstract class AbstractController implements Controller
{
    public function run(Request $request): Response
    {
        return match ($request->stateAs(ControllerState::class, ControllerState::Init)) {
            ControllerState::Init => $this->onInit($request),
            ControllerState::Display => $this->onDisplay($request),
            ControllerState::InitInput => $this->onInitInput($request),
            ControllerState::ReadInput => $this->onReadInput($request),
        };
    }

    /**
     * First run, or the user comes back to the controller: shows the screen
     */
    protected function onInit(Request $request): Response
    {
        return $this->onDisplay($request);
    }

    /**
     * Shows the screen, usually with Response::readInput() to read what the user types
     */
    abstract protected function onDisplay(Request $request): Response;

    /**
     * Asks for input again: shows the screen again
     */
    protected function onInitInput(Request $request): Response
    {
        return $this->onDisplay($request);
    }

    /**
     * Handles the key pressed (Request::key()) and the text typed (Request::content())
     */
    abstract protected function onReadInput(Request $request): Response;
}
