<?php

declare(strict_types=1);

namespace MiniPaviService\Client;

use MiniPaviService\Gateway\Client;
use MiniPaviService\Gateway\ConnectionCode;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Message\Messages;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Client of a browser instead of the gateway: shows the service's screen as an HTML page, a
 * form posting back to its URL (HtmlForm), and lets the user navigate it as on a Minitel. The
 * screen so far is posted back with the page, and a response drawing part of the screen is
 * drawn over it. A key leaving the service (a response whose next URL is another one) is
 * blocked: the screen stays, with FrameworkMessage::WebLeave on line 0.
 *
 * The first request (no key posted) is a connection, as the gateway's: at the default route.
 */
final readonly class HtmlClient implements Client
{
    /**
     * @param HtmlForm $form the page's
     * @param Messages $messages the service's: the error leaving it
     */
    public function __construct(
        private HtmlForm $form,
        private Messages $messages,
    ) {}

    /**
     * The key posted, with the lines typed and the context of the last response, and the page
     * they were posted from; without a key, a connection
     */
    public function exchange(ServerRequestInterface $request, string $serviceUrl): HtmlExchange
    {
        $posted = $this->form->read($request);
        if ($posted === null) {
            return $this->blankPage(new GatewayRequest(ConnectionCode::Connect), $serviceUrl);
        }

        return new HtmlExchange(
            new GatewayRequest($posted->key, $posted->content, $posted->context),
            $posted->page,
            $serviceUrl,
            $this->form,
            $this->messages,
        );
    }

    /**
     * No page yet: an empty screen, no input
     */
    public function unreadable(string $serviceUrl): HtmlExchange
    {
        return $this->blankPage(new GatewayRequest(null), $serviceUrl);
    }

    /**
     * The exchange of a request without a page posted (the first one, or one that couldn't be
     * read): an empty screen, no input
     */
    private function blankPage(GatewayRequest $request, string $serviceUrl): HtmlExchange
    {
        return new HtmlExchange($request, new HtmlPageState(), $serviceUrl, $this->form, $this->messages);
    }
}
