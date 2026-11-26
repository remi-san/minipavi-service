<?php

declare(strict_types=1);

namespace MiniPaviService\Http;

use MiniPaviService\Client\Client;
use MiniPaviService\Client\ClientExchange;
use MiniPaviService\Core\Service;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\Http\Url\UrlAttribute;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Runs the service for an HTTP request, through a client translating between HTTP and the
 * service's values (MiniPaviClient for the gateway, HtmlClient for a browser): the client
 * reads the request, the service responds to it, and the client gives the HTTP response. The
 * URL of the service (UrlAttribute::Service, from WithUrls) goes to the client, for its
 * responses. The context the client carries is signed and checked by the service
 * (ContextSerializer). Nothing to respond to (e.g. a disconnection) is a 204, a response that
 * can't be given (nor the exchange of a request that couldn't be read) a 500.
 */
final readonly class ServiceRunner implements RequestHandlerInterface
{
    private LoggerInterface $logger;

    /**
     * @param ?LoggerInterface $logger where responses that can't be given are logged; none by
     *                                 default
     */
    public function __construct(
        private Service $service,
        private Client $client,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $serviceUrl = UrlAttribute::Service->of($request);
        try {
            $exchange = $this->client->exchange($request, $serviceUrl);
        } catch (\Throwable $e) {
            return $this->respondToUnreadable($serviceUrl, $e);
        }
        try {
            $gatewayRequest = $exchange->request();
        } catch (\Throwable $e) {
            // Responded to through the exchange read (e.g. over the page the browser showed)
            return $this->respond($exchange, $this->service->unreadable($e));
        }

        // The service responds to its own errors (Service::handle())
        return $this->respond($exchange, $this->service->handle($gatewayRequest));
    }

    /**
     * The HTTP response for a request the client couldn't read: the service logs the error and
     * responds to it, through the exchange the client gives for where the request was sent (all
     * that is known of it); 500 if the client can't give it
     */
    private function respondToUnreadable(string $serviceUrl, \Throwable $error): ResponseInterface
    {
        $response = $this->service->unreadable($error);
        try {
            $exchange = $this->client->unreadable($serviceUrl);
        } catch (\Throwable $e) {
            return $this->cannotRespond($e);
        }

        return $this->respond($exchange, $response);
    }

    /**
     * The HTTP response for a gateway response: 204 for none (e.g. a disconnection), 500 if
     * the client can't give it
     */
    private function respond(ClientExchange $exchange, ?GatewayResponse $response): ResponseInterface
    {
        if ($response === null) {
            // E.g. a disconnection
            return new Response(204);
        }

        try {
            return $exchange->respond($response);
        } catch (\Throwable $e) {
            return $this->cannotRespond($e);
        }
    }

    /**
     * A 500, the reason logged as critical
     */
    private function cannotRespond(\Throwable $error): ResponseInterface
    {
        $this->logger->critical('Cannot send the response', ['exception' => $error]);

        return new Response(500);
    }
}
