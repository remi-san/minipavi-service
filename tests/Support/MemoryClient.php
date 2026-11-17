<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Gateway\Client;
use MiniPaviService\Gateway\ClientState;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\Gateway\NoClientState;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Client giving a request (or failing to read it), and answering with the Videotex of the
 * answer; keeps the requests and answers it gave responses for in $responded
 *
 * @implements Client<NoClientState>
 */
final class MemoryClient implements Client
{
    /** @var list<array{GatewayRequest<NoClientState>, GatewayResponse}> */
    public array $responded = [];

    public function __construct(
        /** @var GatewayRequest<ClientState>|\Throwable */
        private GatewayRequest|\Throwable $request,
        private ?\Throwable $responseFailure = null,
    ) {}

    public function request(ServerRequestInterface $request, string $serviceUrl): GatewayRequest
    {
        if ($this->request instanceof \Throwable) {
            throw $this->request;
        }

        return new GatewayRequest(
            $this->request->key,
            $this->request->content,
            $this->request->context,
            $this->request->terminal,
            $serviceUrl,
        );
    }

    public function initialState(): NoClientState
    {
        return new NoClientState();
    }

    public function response(GatewayRequest $request, GatewayResponse $answer): ResponseInterface
    {
        if ($this->responseFailure !== null) {
            throw $this->responseFailure;
        }
        $this->responded[] = [$request, $answer];

        return new Response(200, [], $answer->vdt->toString());
    }
}
