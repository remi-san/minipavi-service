<?php

declare(strict_types=1);

namespace MiniPaviService\Client;

use MiniPaviService\Gateway\Client;
use MiniPaviService\Gateway\GatewayAnswer;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Vdt\HtmlRenderer;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Client of a browser instead of the gateway: asks the service for a screen, and gives the
 * answer drawn as an HTML page (HtmlRenderer). No interaction: a connection, or with the
 * query's route (?route=games), a request on that route, its controller at its initial state.
 */
final class HtmlClient implements Client
{
    /**
     * A connection, or a request on the route of the query: the context of a user who just
     * got there, without key nor input
     */
    public function request(ServerRequestInterface $request, string $serviceUrl): GatewayRequest
    {
        $route = $request->getQueryParams()['route'] ?? null;
        if (!\is_string($route) || $route === '') {
            return new GatewayRequest(GatewayRequest::FCTN_CONNECT, serviceUrl: $serviceUrl);
        }

        return new GatewayRequest('', [], (string) json_encode(['route' => $route]), serviceUrl: $serviceUrl);
    }

    /**
     * The screen of the answer, as an HTML page titled with the service's URL
     */
    public function response(GatewayRequest $request, GatewayAnswer $answer): ResponseInterface
    {
        return new Response(
            200,
            ['Content-Type' => 'text/html; charset=utf-8'],
            (new HtmlRenderer())->render($answer->vdt->screen(), $request->serviceUrl),
        );
    }
}
