<?php

declare(strict_types=1);

namespace MiniPaviService\Client;

use MiniPaviService\Gateway\Client;
use MiniPaviService\Gateway\GatewayAnswer;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Http\RequestUrl;
use MiniPaviService\Http\ServerVariable;
use MiniPaviService\Vdt\HtmlRenderer;

/**
 * Client of a browser instead of the gateway: asks the service for a screen, and shows the
 * answer drawn as an HTML page (HtmlRenderer). No interaction: a connection, or with the
 * query string's route (?route=games), a request on that route, its controller at its
 * initial state.
 */
final class HtmlClient implements Client
{
    private RequestUrl $url;

    /**
     * @param ?RequestUrl $url URLs of the service, by default from the environment: the
     *                         title of the page
     */
    public function __construct(?RequestUrl $url = null)
    {
        $this->url = $url ?? RequestUrl::fromEnvironment();
    }

    /**
     * A connection, or a request on the route of the query string: the context of a user who
     * just got there, without key nor input
     */
    public function receive(): GatewayRequest
    {
        $route = ServerVariable::query('route');
        if ($route === null || $route === '') {
            return new GatewayRequest(GatewayRequest::FCTN_CONNECT);
        }

        return new GatewayRequest('', [], (string) json_encode(['route' => $route]));
    }

    /**
     * Shows the screen of the answer, as an HTML page
     */
    public function send(GatewayAnswer $answer): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo (new HtmlRenderer())->render($answer->vdt->screen(), $this->url->current());
    }
}
