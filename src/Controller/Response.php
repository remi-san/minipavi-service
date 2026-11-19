<?php

declare(strict_types=1);

namespace MiniPaviService\Controller;

use MiniPaviService\Controller\Parameters\Parameters;
use MiniPaviService\Controller\Response\ChangePage;
use MiniPaviService\Controller\Response\DisplayPage;
use MiniPaviService\Controller\Response\RunCommand;
use MiniPaviService\Gateway\CallMode;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\Gateway\InputCommand;
use MiniPaviService\Gateway\ServiceCallCommand;
use MiniPaviService\Gateway\WaitForKeyCommand;
use MiniPaviService\Vdt\Vdt;

/**
 * What a controller returns: what comes next (continueWith()), through a Flow. Responses are
 * readonly and don't change the context: they ask the Flow to keep the state and parameters
 * of this controller for its next run (KeepingResponse), and to answer the gateway or run
 * another controller.
 *
 * The named constructors give each response the state it usually keeps: read the next
 * input after showing something, start again after leaving. Pass $state or $parameters
 * (by name) to keep others: a case of the controller's State enum (ControllerState for an
 * AbstractController), and a Parameters object of the class it declares (null: the
 * defaults).
 */
abstract readonly class Response
{
    /**
     * Sends Videotex content to the gateway; the next key comes back to this controller
     */
    public static function display(
        Vdt $vdt,
        State $state = ControllerState::ReadInput,
        ?Parameters $parameters = null,
    ): DisplayPage {
        return new DisplayPage($state, $parameters, $vdt);
    }

    /**
     * Sends Videotex content and an input command (Page::inputCommand(); null for a page
     * without input: only the content is sent); what is typed comes back to this controller
     */
    public static function readInput(
        Vdt $vdt,
        ?InputCommand $command,
        State $state = ControllerState::ReadInput,
        ?Parameters $parameters = null,
    ): RunCommand {
        return new RunCommand($state, $parameters, $vdt, $command ?? new WaitForKeyCommand());
    }

    /**
     * Switches to the controller of another route, and runs it at once: it starts at its
     * initial state with parameters, without the user's input. This controller is reset.
     *
     * @param ?Parameters $parameters parameters of the target controller, of the class it
     *                                declares; null: their defaults
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
    public static function connectTo(
        string $url,
        Vdt $vdt = new Vdt(),
        ?State $state = null,
        ?Parameters $parameters = null,
    ): RunCommand {
        return new RunCommand($state, $parameters, $vdt, new ServiceCallCommand($url, CallMode::Connect));
    }

    /**
     * What comes next: keep a state and parameters for this controller (Flow::keep()), then
     * answer the gateway (Flow::answer()) or run another controller (Flow::goTo()); gives the
     * answer the request ends with
     */
    abstract public function continueWith(Flow $flow): GatewayResponse;
}
