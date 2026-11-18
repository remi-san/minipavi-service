<?php

declare(strict_types=1);

namespace MiniPaviService\Gateway;

use Psr\Http\Message\ResponseInterface;

/**
 * One HTTP request as a client reads it: what it asks the service (request()), and how the
 * service's answer goes back (respond()). What the client needs to answer, besides what the
 * service gets (e.g. the page HtmlClient's browser showed), stays in the exchange.
 */
interface ClientExchange
{
    /**
     * What the HTTP request asks the service to answer
     */
    public function request(): GatewayRequest;

    /**
     * The HTTP response giving the service's answer
     */
    public function respond(GatewayResponse $answer): ResponseInterface;
}
