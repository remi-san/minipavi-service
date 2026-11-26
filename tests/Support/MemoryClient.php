<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Client\Client;
use MiniPaviService\Client\ClientExchange;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Gateway\GatewayResponse;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Client giving a request (or failing to read it), and responding with the Videotex of the
 * response (or failing to give the request, or the response, once read); keeps the requests and
 * responds to it gave responses for in $responded, and the service URL of each exchange in
 * $serviceUrls. It can also fail to give the exchange of a request it couldn't read.
 */
final class MemoryClient implements Client
{
    /** @var list<array{GatewayRequest, GatewayResponse}> */
    public array $responded = [];

    /** @var list<string> */
    public array $serviceUrls = [];

    public function __construct(
        private GatewayRequest|\Throwable $request,
        private ?\Throwable $responseFailure = null,
        private ?\Throwable $requestFailure = null,
        private ?\Throwable $unreadableFailure = null,
    ) {}

    public function exchange(ServerRequestInterface $request, string $serviceUrl): ClientExchange
    {
        $this->serviceUrls[] = $serviceUrl;
        if ($this->request instanceof \Throwable) {
            throw $this->request;
        }

        return $this->exchangeOf($this->request);
    }

    public function unreadable(string $serviceUrl): ClientExchange
    {
        $this->serviceUrls[] = $serviceUrl;
        if ($this->unreadableFailure !== null) {
            throw $this->unreadableFailure;
        }

        return $this->exchangeOf(new GatewayRequest(null));
    }

    private function exchangeOf(GatewayRequest $request): ClientExchange
    {
        return new class($this, $request, $this->responseFailure, $this->requestFailure) implements ClientExchange {
            public function __construct(
                private readonly MemoryClient $client,
                private readonly GatewayRequest $request,
                private readonly ?\Throwable $responseFailure,
                private readonly ?\Throwable $requestFailure,
            ) {}

            public function request(): GatewayRequest
            {
                if ($this->requestFailure !== null) {
                    throw $this->requestFailure;
                }

                return $this->request;
            }

            public function respond(GatewayResponse $response): ResponseInterface
            {
                if ($this->responseFailure !== null) {
                    throw $this->responseFailure;
                }
                $this->client->responded[] = [$this->request, $response];

                return new Response(200, [], $response->vdt->toString());
            }
        };
    }
}
