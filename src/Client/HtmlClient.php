<?php

declare(strict_types=1);

namespace MiniPaviService\Client;

use MiniPaviService\Gateway\Client;
use MiniPaviService\Gateway\ConnectionCode;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\Message\FrameworkMessage;
use MiniPaviService\Message\Messages;
use MiniPaviService\Vdt\Vdt;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Client of a browser instead of the gateway: shows the service's screen as an HTML page, a
 * form posting back to its URL (HtmlForm), and lets the user navigate it as on a Minitel. The
 * screen so far is posted back with the page, and an answer drawing part of the screen is
 * drawn over it. A key leaving the service (an answer whose next URL is another one) is
 * blocked: the screen stays, with FrameworkMessage::WebLeave on line 0.
 *
 * The first request (no key posted) is a connection, as the gateway's: at the default route.
 *
 * @implements Client<HtmlPageState>
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
     * The key posted, with the lines typed and the context of the last answer; without one, a
     * connection
     */
    public function request(ServerRequestInterface $request, string $serviceUrl): GatewayRequest
    {
        $posted = $this->form->read($request);
        if ($posted === null) {
            return new GatewayRequest(
                ConnectionCode::Connect,
                serviceUrl: $serviceUrl,
                clientState: $this->initialState(),
            );
        }

        return new GatewayRequest(
            $posted->key,
            $posted->content,
            $posted->context,
            serviceUrl: $serviceUrl,
            clientState: $posted->page,
        );
    }

    /**
     * No page yet: an empty screen, no input
     */
    public function initialState(): HtmlPageState
    {
        return new HtmlPageState();
    }

    /**
     * The page of the answer: its screen drawn over the screen so far, its input fields and the
     * function keys; or, for an answer leaving the service, the page as it was, with the error
     * on line 0
     */
    public function response(GatewayRequest $request, GatewayResponse $answer): ResponseInterface
    {
        $page = $request->clientState;
        $html =
            $answer->next !== null && $answer->next !== $request->serviceUrl
                // Blocked: the user stays where they were
                ? $this->form->render(
                    $page->screen->append(Vdt::line0($this->messages->get(FrameworkMessage::WebLeave))),
                    $request->context,
                    $page->command,
                    $request->serviceUrl,
                )
                : $this->form->render(
                    $page->screen->append($answer->vdt),
                    $answer->context,
                    $answer->command,
                    $request->serviceUrl,
                );

        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }
}
