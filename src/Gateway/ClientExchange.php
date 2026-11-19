<?php

declare(strict_types=1);

namespace MiniPaviService\Gateway;

use Psr\Http\Message\ResponseInterface;

/**
 * One HTTP request as a client reads it: what it asks the service (request()), and how the
 * service's response goes back (respond()). What the client needs to respond, besides what the
 * service gets (e.g. the page HtmlClient's browser showed), stays in the exchange.
 */
interface ClientExchange
{
    /**
     * What the HTTP request asks the service to respond to
     */
    public function request(): GatewayRequest;

    /**
     * The HTTP response giving the service's response
     */
    public function respond(GatewayResponse $response): ResponseInterface;
}
