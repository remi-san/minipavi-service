<?php

declare(strict_types=1);

namespace MiniPaviService\Controller;

use MiniPaviService\Controller;

/**
 * Controller running the method of its state: onDisplay() to show its screen, and
 * onReadInput() to handle what the user typed or pressed, the two to write. onInit() and
 * onInitInput() show the screen too, unless overridden.
 *
 * A state the controller doesn't know (e.g. kept in the context by an older version of the
 * service) starts it again, as STATE_INIT. To add states, override run() and call
 * parent::run() for the others.
 */
abstract class AbstractController implements Controller
{
    public function run(Request $request): Response
    {
        return match ($request->state()) {
            self::STATE_DISPLAY => $this->onDisplay($request),
            self::STATE_INIT_INPUT => $this->onInitInput($request),
            self::STATE_READ_INPUT => $this->onReadInput($request),
            default => $this->onInit($request),
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
