<?php

declare(strict_types=1);

namespace MiniPaviService\Http;

use MiniPaviService\Core\Service;
use MiniPaviService\Gateway\Client;
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
 * reads the request, the service answers it, and the client gives the response. The URL of
 * the service (UrlAttribute::Service, from WithUrls) goes to the client, for its answers.
 * The context the client carries is signed and checked by the service (ContextSerializer).
 * Nothing to answer (e.g. a disconnection) is a 204, an answer that can't be given a 500.
 *
 */
final readonly class ServiceRunner implements RequestHandlerInterface
{
    private LoggerInterface $logger;

    /**
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
            $exchange = $this->client->exchange($request, $serviceUrl);
            $answer = $this->service->handle($exchange->request());
        } catch (\Throwable $e) {
            $exchange = $this->client->unreadable($serviceUrl);
            $answer = $this->service->error($e);
        }
        if ($answer === null) {
            // E.g. a disconnection
            return new Response(204);
        }

        try {
            return $exchange->respond($answer);
        } catch (\Throwable $e) {
            $this->logger->critical('Cannot send the answer: ' . $e->getMessage(), ['exception' => $e]);

            return new Response(500);
        }
    }
}
