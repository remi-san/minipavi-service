<?php

declare(strict_types=1);

namespace MiniPaviService\Client\MiniPavi;

use MiniPaviService\Gateway\CallMode;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Gateway\InputCommand;
use MiniPaviService\Vdt\Vdt;

/**
 * The MiniPavi gateway, as MiniPaviClient reads its request and sends it the response:
 * MiniPaviCliGateway, through the static MiniPaviCli
 */
interface MiniPaviGateway
{
    /**
     * Reads the gateway's request (the body of the HTTP request). A background call
     * (ConnectionCode::BackgroundCall) is read too: the service gives it no response
     *
     * @throws \Exception if it is not valid JSON
     */
    public function read(): GatewayRequest;

    /**
     * Sends the response: the content and the context, the URL the gateway calls next, and
     * what it does before
     *
     * @param ?InputCommand $input field the gateway reads
     * @param ?CallMode $directCall calls next at once, without waiting for the user
     *
     * @return string what is sent to the gateway: the body of the HTTP response
     */
    public function send(
        Vdt $vdt,
        string $next,
        string $context,
        ?InputCommand $input = null,
        ?CallMode $directCall = null,
    ): string;
}
