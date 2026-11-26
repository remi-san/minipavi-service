<?php

declare(strict_types=1);

namespace MiniPaviService\Client;

use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Gateway\GatewayResponse;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * A request of the MiniPavi gateway (MiniPaviClient), responded to through the gateway that read
 * it (MiniPaviCommandWriter): a response that calls no other service calls this one again, at
 * the URL the request was sent to
 */
final readonly class MiniPaviExchange implements ClientExchange
{
    /**
     * @param MiniPaviGateway $gateway the gateway that read the request, sent the response
     * @param GatewayRequest $request what it read of the request
     * @param string $serviceUrl URL the request was sent to: the next URL of responses without
     *                           one
     */
    public function __construct(
        private MiniPaviGateway $gateway,
        private GatewayRequest $request,
        private string $serviceUrl,
    ) {}

    /**
     * The gateway's request, as it was read
     */
    public function request(): GatewayRequest
    {
        return $this->request;
    }

    /**
     * The response, as the gateway is sent it (MiniPaviCommandWriter), as the body of the HTTP
     * response
     */
    public function respond(GatewayResponse $response): ResponseInterface
    {
        return new Response(200, [], (new MiniPaviCommandWriter($this->gateway, $response, $this->serviceUrl))->body());
    }
}
