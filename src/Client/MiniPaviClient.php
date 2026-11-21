<?php

declare(strict_types=1);

namespace MiniPaviService\Client;

use MiniPaviService\Gateway\Client;
use MiniPaviService\Gateway\GatewayRequest;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Client of the MiniPavi gateway, through its MiniPaviGateway (MiniPaviCliGateway, the static
 * MiniPaviCli), which reads the gateway's request and sends it the response itself: the
 * exchange (MiniPaviExchange) and the commands (MiniPaviCommandWriter) go through it.
 */
final readonly class MiniPaviClient implements Client
{
    /**
     * @param MiniPaviGateway $gateway reads the gateway's request and sends it the response
     */
    public function __construct(
        private MiniPaviGateway $gateway = new MiniPaviCliGateway(),
    ) {}

    /**
     * The request the gateway reads (MiniPaviGateway::read()), a background call included
     *
     * @throws \Exception if it is not valid JSON
     */
    public function exchange(ServerRequestInterface $request, string $serviceUrl): MiniPaviExchange
    {
        return new MiniPaviExchange($this->gateway, $this->gateway->read(), $serviceUrl);
    }

    public function unreadable(string $serviceUrl): MiniPaviExchange
    {
        return new MiniPaviExchange($this->gateway, new GatewayRequest(null), $serviceUrl);
    }
}
