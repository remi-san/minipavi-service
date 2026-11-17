<?php

declare(strict_types=1);

namespace MiniPaviService\Gateway;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Translates between HTTP and what the service handles: the HTTP request into a
 * GatewayRequest, the GatewayAnswer into an HTTP response. Implemented in Client\:
 * MiniPaviClient for the MiniPavi gateway, HtmlClient for a browser.
 */
interface Client
{
    /**
     * The request the HTTP request asks the service to answer
     *
     * @param string $serviceUrl URL of the service (Http\UrlAttribute::Service), kept in the
     *                           GatewayRequest
     *
     * @throws \Exception if it can't be read
     */
    public function request(ServerRequestInterface $request, string $serviceUrl): GatewayRequest;

    /**
     * The HTTP response giving the answer to the request
     */
    public function response(GatewayRequest $request, GatewayAnswer $answer): ResponseInterface;
}
