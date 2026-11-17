<?php

declare(strict_types=1);

namespace MiniPaviService\Http;

use MiniPaviService\Core\Service;
use MiniPaviService\Gateway\Client;
use MiniPaviService\Gateway\ClientState;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Http\Url\UrlAttribute;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Runs the service for an HTTP request, through a client translating between HTTP and the
 * service's values (MiniPaviClient for the gateway, HtmlClient for a browser): the client
 * reads the request, the service answers it, and the client gives the response. The URL of
 * the service (UrlAttribute::Service, from WithUrls) goes to the client, in the GatewayRequest.
 *
 * @template TState of ClientState
 */
final readonly class ServiceRunner implements RequestHandlerInterface
{
    private LoggerInterface $logger;

    /**
     * @param Client<TState> $client
     * @param ?LoggerInterface $logger where answers that can't be given are logged; none by
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
            $gatewayRequest = $this->client->request($request, $serviceUrl);
            $answer = $this->service->handle($gatewayRequest);
        } catch (\Throwable $e) {
            // The request couldn't be read: all that is known of it is where it was sent
            $gatewayRequest = new GatewayRequest('', serviceUrl: $serviceUrl, clientState: $this->client->initialState());
            $answer = $this->service->error($e);
        }
        if ($answer === null) {
            return new EmptyHttpResponse();
        }

        try {
            return $this->client->response($gatewayRequest, $answer);
        } catch (\Throwable $e) {
            $this->logger->critical('Cannot send the answer: ' . $e->getMessage(), ['exception' => $e]);

            return new EmptyHttpResponse();
        }
    }
}
