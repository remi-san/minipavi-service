<?php

declare(strict_types=1);

namespace MiniPaviService\Client;

use MiniPaviService\Gateway\CommandSender;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\Gateway\InputCommand;
use MiniPaviService\Gateway\ServiceCallCommand;
use MiniPaviService\Gateway\WaitForKeyCommand;
use MiniPaviService\Message\FrameworkMessage;
use MiniPaviService\Message\Messages;
use MiniPaviService\Vdt\Vdt;

/**
 * Renders an answer as HtmlClient's next page (HtmlExchange), sending its command as what
 * the page shows. The answer's screen is drawn over the one the page showed, with the field of an
 * input command; a call to another service is blocked, the page shown as it was, with
 * FrameworkMessage::WebLeave on line 0.
 *
 * @implements CommandSender<string>
 *
 * @internal
 */
final readonly class HtmlCommandRenderer implements CommandSender
{
    /**
     * @param HtmlPageState $page what the page showed
     * @param string $context the context the page posted: kept when the answer is blocked
     * @param string $serviceUrl URL of the service: the page's title, and where it stays
     */
    public function __construct(
        private GatewayResponse $answer,
        private HtmlPageState $page,
        private string $context,
        private string $serviceUrl,
        private HtmlForm $form,
        private Messages $messages,
    ) {}

    /**
     * The next page
     */
    public function html(): string
    {
        return $this->answer->command->sendWith($this);
    }

    /**
     * The answer, without a field
     */
    public function waitForKey(WaitForKeyCommand $command): string
    {
        return $this->shown(command: null);
    }

    /**
     * The answer, with the field
     */
    public function readInput(InputCommand $command): string
    {
        return $this->shown($command);
    }

    /**
     * This service again: the answer, as when waiting for a key (the page doesn't call
     * it by itself); another one: blocked, the user stays where they were
     */
    public function callService(ServiceCallCommand $command): string
    {
        if ($command->url === $this->serviceUrl) {
            return $this->shown(command: null);
        }

        return $this->form->render(
            $this->page->screen->append(Vdt::line0($this->messages->get(FrameworkMessage::WebLeave))),
            $this->context,
            $this->page->command,
            $this->serviceUrl,
        );
    }

    /**
     * The answer's screen over the page's, with the field of command, if any
     */
    private function shown(?InputCommand $command): string
    {
        return $this->form->render(
            $this->page->screen->append($this->answer->vdt),
            $this->answer->context,
            $command,
            $this->serviceUrl,
        );
    }
}
