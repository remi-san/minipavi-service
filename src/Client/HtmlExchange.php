<?php

declare(strict_types=1);

namespace MiniPaviService\Client;

use MiniPaviService\Gateway\ClientExchange;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\Message\Messages;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * A request of HtmlClient's page, responded to with the next page: the response's screen drawn
 * over the screen the page showed, its input fields and the function keys; or, for a response
 * calling another service (ServiceCallCommand), the page as it was, with
 * FrameworkMessage::WebLeave on line 0
 */
final readonly class HtmlExchange implements ClientExchange
{
    /**
     * @param GatewayRequest $request what the page posted: the key, the lines typed, the context
     * @param HtmlPageState $page what the page showed (nothing for its first request)
     * @param string $serviceUrl URL of the service: the page's title, and where it stays
     * @param HtmlForm $form the page's, drawing the next one
     * @param Messages $messages the service's: the error leaving it
     */
    public function __construct(
        private GatewayRequest $request,
        private HtmlPageState $page,
        private string $serviceUrl,
        private HtmlForm $form,
        private Messages $messages,
    ) {}

    /**
     * What the page posted, without the page itself
     */
    public function request(): GatewayRequest
    {
        return $this->request;
    }

    /**
     * The next page (HtmlCommandRenderer): the response's screen drawn over the one the page
     * showed, its input fields and the keys; for a response calling another service, the page
     * as it was, with FrameworkMessage::WebLeave on line 0
     */
    public function respond(GatewayResponse $response): ResponseInterface
    {
        $html = (new HtmlCommandRenderer(
            $response,
            $this->page,
            $this->request->context,
            $this->serviceUrl,
            $this->form,
            $this->messages,
        ))->html();

        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }
}
