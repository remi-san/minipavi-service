<?php

declare(strict_types=1);

namespace MiniPaviService\Client;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Translates between HTTP and what the service handles: reads an HTTP request into a
 * ClientExchange, which gives the GatewayRequest to the service and its GatewayResponse back
 * as an HTTP response. Implemented in Client\: MiniPaviClient for the MiniPavi gateway,
 * HtmlClient for a browser.
 */
interface Client
{
    /**
     * The exchange of an HTTP request
     *
     * @param string $serviceUrl URL of the service (Http\Url\UrlAttribute::Service), e.g. the
     *                           next URL of responses without one
     *
     * @throws \Exception if the request can't be read
     */
    public function exchange(ServerRequestInterface $request, string $serviceUrl): ClientExchange;

    /**
     * The exchange of a request it couldn't read (exchange() threw): all that is known of it is
     * where it was sent
     */
    public function unreadable(string $serviceUrl): ClientExchange;
}
