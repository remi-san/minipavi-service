<?php

declare(strict_types=1);

namespace MiniPaviService\Controller;

use MiniPaviService\Controller\Response\ChangePage;
use MiniPaviService\Controller\Response\DisplayPage;
use MiniPaviService\Controller\Response\RunCommand;
use MiniPaviService\Controller\Response\RunExternalService;
use MiniPaviService\Gateway\GatewayAnswer;
use MiniPaviService\Page\InputCommand;

/**
 * What a controller returns: the state and parameters it keeps for its next run, in the
 * context, and what comes next (continueWith()). Responses are readonly and don't change the
 * context: the runtime keeps their state and parameters, then they say what comes next
 * through a Flow.
 *
 * The named constructors give each response the state it usually keeps: read the next
 * input after showing something, start again after leaving. Pass $state or $parameters
 * (by name) to keep others: a State (ControllerState, or the controller's own enum), and a
 * Parameters object (null: none).
 */
abstract readonly class Response
{
    public function __construct(
        private State $state,
        private ?Parameters $parameters,
    ) {}

    /**
     * Sends Videotex content to the gateway; the next key comes back to this controller
     */
    public static function display(string $vdt, State $state = ControllerState::ReadInput, ?Parameters $parameters = null): DisplayPage
    {
        return new DisplayPage($state, $parameters, $vdt);
    }

    /**
     * Sends Videotex content and an input command (Page::inputCommand(); null for a page
     * without input: only the content is sent); what is typed comes back to this controller
     */
    public static function readInput(string $vdt, ?InputCommand $command, State $state = ControllerState::ReadInput, ?Parameters $parameters = null): RunCommand
    {
        return new RunCommand($state, $parameters, $vdt, $command);
    }

    /**
     * Switches to the controller of another route, and runs it at once: it starts at
     * ControllerState::Init with parameters, without the user's input. This controller is
     * reset.
     *
     * @param ?Parameters $parameters parameters of the target controller; null: none
     */
    public static function goTo(string $route, ?Parameters $parameters = null): ChangePage
    {
        return new ChangePage($route, $parameters);
    }

    /**
     * Connects the user to another service, at url, after showing vdt (e.g. a message on
     * line 0). state and parameters are kept for this controller, for when the user
     * comes back.
     */
    public static function connectTo(string $url, string $vdt = '', State $state = ControllerState::Init, ?Parameters $parameters = null): RunExternalService
    {
        return new RunExternalService($state, $parameters, $vdt, $url, CallMode::Connect);
    }

    /**
     * What comes next, once the context keeps this response's state and parameters: answer
     * the gateway (Flow::answer()) or run another controller (Flow::goTo()); gives the answer
     * the request ends with
     */
    abstract public function continueWith(Flow $flow): GatewayAnswer;

    public function state(): State
    {
        return $this->state;
    }

    /**
     * Parameters this controller keeps; null: none
     */
    public function parameters(): ?Parameters
    {
        return $this->parameters;
    }
}
