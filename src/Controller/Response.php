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
 * again after leaving (new ChangePage(), RunCommand::connectTo()). Pass $state or $parameters
 * after the content and the command (by name to give only one) to keep others: a case of the
 * controller's State enum (ControllerState for an AbstractController), and a Parameters
 * object of the class it declares (null: the defaults). A service can add its own responses
 * the same way.
 */
abstract readonly class Response
{
    /**
     * What comes next: keep a state and parameters for this controller (Flow::keep()), then
     * respond to the gateway (Flow::respond()) or run another controller (Flow::goTo()); gives
     * the gateway response the request ends with
     */
    abstract public function continueWith(Flow $flow): GatewayResponse;
}
