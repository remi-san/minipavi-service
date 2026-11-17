<?php

declare(strict_types=1);

namespace MiniPaviService\Controller;

/**
 * State of a controller: a case of a string-backed enum implementing this interface, whose
 * value is what the context keeps. ControllerState has the ones AbstractController runs; a
 * controller with states of its own names them in its own enum, read with
 * Request::stateAs().
 */
interface State extends \BackedEnum {}
