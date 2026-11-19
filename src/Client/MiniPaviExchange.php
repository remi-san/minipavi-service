<?php

declare(strict_types=1);

namespace MiniPaviService\Client;

use MiniPaviService\Gateway\ClientExchange;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Gateway\GatewayResponse;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * A request of the MiniPavi gateway (MiniPaviClient), responded to as MiniPaviCli::send()
 * writes it (MiniPaviCommandWriter): a response that calls no other service calls this one
 * again, at the URL the request was sent to
 */
final readonly class MiniPaviExchange implements ClientExchange
{
    /**
     * @param GatewayRequest $request what MiniPaviCli read of the gateway's request
     * @param string $serviceUrl URL the request was sent to: the next URL of responses without
     *                           one
     */
    public function __construct(
        private GatewayRequest $request,
        private string $serviceUrl,
    ) {}

    /**
     * The gateway's request, as MiniPaviCli read it
     */
    public function request(): GatewayRequest
    {
        return $this->request;
    }

    /**
     * The response, as MiniPaviCli::send() writes it (MiniPaviCommandWriter), captured as the
     * body of the HTTP response
     */
    public function respond(GatewayResponse $response): ResponseInterface
    {
        return new Response(200, [], (new MiniPaviCommandWriter($response, $this->serviceUrl))->body());
    }
}
