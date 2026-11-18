<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Gateway\Client;
use MiniPaviService\Gateway\ClientExchange;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Gateway\GatewayResponse;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Client giving a request (or failing to read it), and answering with the Videotex of the
 * answer (or failing to give the request, or the response, once read); keeps the requests and
 * answers it gave responses for in $responded, and the service URL of each exchange in
 * $serviceUrls
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

            public function respond(GatewayResponse $answer): ResponseInterface
            {
                if ($this->responseFailure !== null) {
                    throw $this->responseFailure;
                }
                $this->client->responded[] = [$this->request, $answer];

                return new Response(200, [], $answer->vdt->toString());
            }
        };
    }
}
