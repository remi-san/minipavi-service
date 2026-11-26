<?php

declare(strict_types=1);

namespace MiniPaviService\Gateway\Command;

/**
 * How the gateway calls another service, right away (ServiceCallCommand)
 */
enum CallMode: string
{
    // As a new connection: the service is called with fctn DIRECTCNX
    case Connect = 'yes-cnx';
    // As the continuation of the session: the service is called with fctn DIRECT
    case Direct = 'yes';
}
