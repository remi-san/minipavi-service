<?php

declare(strict_types=1);

namespace MiniPaviService\Controller;

use MiniPaviService\Gateway\GatewayResponse;

/**
 * What a controller returns: what comes next (continueWith()), through a Flow. Responses are
 * readonly and don't change the context: they ask the Flow to keep the state and parameters
 * of this controller for its next run (KeepingResponse), and to respond to the gateway or run
 * another controller.
 *
 * Each response is built from its own class, which gives it the state it usually keeps: read
 * the next input after showing something (new DisplayPage(), RunCommand::readInput()), start
 * again after leaving (new ChangePage(), which resets this controller, and
 * RunCommand::connectTo()). A KeepingResponse takes $state or $parameters after the content
 * and the command to keep others (by name to give only one, except for new RunCommand(),
 * which requires its state): a case of the controller's State
 * enum (ControllerState for an AbstractController: a controller with its own enum gives its
 * state, as the defaults are ControllerState's), and a Parameters object of the class it
 * declares (null: the defaults). ChangePage's parameters are the target controller's. A
 * service can add its own responses the same way.
 *
 * A response is typed by the states it keeps (TState: Response<ControllerState>, or the
 * controller's enum), and Controller::run() returns a Response of the controller's, so that
 * static analysis reports a response keeping another enum's state, e.g. the ReadInput a
 * controller with its own enum didn't replace. ChangePage keeps none (Response<never>), so it
 * is any controller's.
 *
 * @template-covariant TState of State
 */
abstract readonly class Response
{
    /**
     * What comes next: keep a state and parameters for this controller (Flow::keep()), then
     * respond to the gateway (Flow::respond()) or run another controller (Flow::goTo()); gives
     * the gateway response the request ends with
     *
     * @param Flow<TState> $flow
     */
    abstract public function continueWith(Flow $flow): GatewayResponse;
}
