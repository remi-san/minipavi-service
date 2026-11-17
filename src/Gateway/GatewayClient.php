<?php

declare(strict_types=1);

namespace MiniPaviService\Gateway;

/**
 * What a service is run for: reads the request (receive()), and sends the answer (send()).
 * MiniPaviClient talks to the MiniPavi gateway; Http\HtmlClient shows a browser the screen.
 */
interface GatewayClient
{
    /**
     * The request to answer
     *
     * @throws \Exception if it can't be read
     */
    public function receive(): GatewayRequest;

    /**
     * Sends the answer to the request
     */
    public function send(GatewayAnswer $answer): void;
}
