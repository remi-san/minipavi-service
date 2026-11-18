<?php

declare(strict_types=1);

namespace MiniPaviService\Client;

use MiniPaviService\Gateway\ClientExchange;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\Message\FrameworkMessage;
use MiniPaviService\Message\Messages;
use MiniPaviService\Vdt\Vdt;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * A request of HtmlClient's page, answered with the next page: the answer's screen drawn over
 * the screen the page showed, its input fields and the function keys; or, for an answer
 * leaving the service, the page as it was, with FrameworkMessage::WebLeave on line 0
 */
final readonly class HtmlExchange implements ClientExchange
{
    /**
     * @param HtmlPageState $page what the page showed (nothing for its first request)
     * @param string $serviceUrl URL of the service: the page's title, and where it stays
     * @param Messages $messages the service's: the error leaving it
     */
    public function __construct(
        private GatewayRequest $request,
        private HtmlPageState $page,
        private string $serviceUrl,
        private HtmlForm $form,
        private Messages $messages,
    ) {}

    public function request(): GatewayRequest
    {
        return $this->request;
    }

    public function respond(GatewayResponse $answer): ResponseInterface
    {
        $html =
            $answer->next !== null && $answer->next !== $this->serviceUrl
                // Blocked: the user stays where they were
                ? $this->form->render(
                    $this->page->screen->append(Vdt::line0($this->messages->get(FrameworkMessage::WebLeave))),
                    $this->request->context,
                    $this->page->command,
                    $this->serviceUrl,
                )
                : $this->form->render(
                    $this->page->screen->append($answer->vdt),
                    $answer->context,
                    $answer->command,
                    $this->serviceUrl,
                );

        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }
}
