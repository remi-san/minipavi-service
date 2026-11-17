<?php

declare(strict_types=1);

namespace MiniPaviService\Gateway;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Translates between HTTP and what the service handles: the HTTP request into a
 * GatewayRequest, the GatewayResponse into an HTTP response. What the client needs to answer,
 * besides what the service gets, is its state (TState), read with the request and given back
 * in it. Implemented in Client\: MiniPaviClient for the MiniPavi gateway, HtmlClient for a
 * browser.
 *
 * @template TState of ClientState
 */
interface Client
{
    /**
     * The request the HTTP request asks the service to answer, with the client's state
     *
     * @param string $serviceUrl URL of the service (Http\Url\UrlAttribute::Service), kept in
     *                           the GatewayRequest
     *
     * @return GatewayRequest<TState>
     *
     * @throws \Exception if it can't be read
     */
    public function request(ServerRequestInterface $request, string $serviceUrl): GatewayRequest;

    /**
     * State of the client for a request it couldn't read (request() threw)
     *
     * @return TState
     */
    public function initialState(): ClientState;

    /**
     * The HTTP response giving the answer to the request
     *
     * @param GatewayRequest<TState> $request
     */
    public function response(GatewayRequest $request, GatewayResponse $answer): ResponseInterface;
}
