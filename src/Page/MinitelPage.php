<?php

declare(strict_types=1);

namespace MiniPaviService\Page;

use MiniPaviService\Gateway\InputCommand;
use MiniPaviService\Message\Message;
use MiniPaviService\Message\Messages;
use MiniPaviService\Minitel\InputField;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Page;
use MiniPaviService\Vdt\Vdt;
use MiniPaviService\Vdt\Videotex;

/**
 * Page of the page XML format (PageXmlParser): sets the Minitel up, clears the screen and
 * draws its content, static or a template; reads its input field if it has one; has its
 * messages, the service's with those it redefines
 */
final readonly class MinitelPage implements Page
{
    /**
     * @param Messages $messages the page's
     * @param ?InputField $input its field; null: none
     */
    public function __construct(
        private Content $content,
        private Messages $messages,
        private ?InputField $input = null,
        private Setup $setup = new Setup(),
    ) {}

    /**
     * The setup, a clear screen, the cursor off, then the content, drawn for data (the
     * variables of a template; a static page doesn't use them)
     *
     * @param array<string, mixed> $data
     */
    public function display(Terminal $terminal, array $data = []): Vdt
    {
        return Vdt::join(
            $this->setup->toVdt(),
            Vdt::clearScreen(),
            new Vdt(Videotex::CURSOR_OFF),
            $this->content->display($data)->toVdt($terminal),
        );
    }

    /**
     * @param string[] $prefill
     */
    public function inputCommand(array $prefill = []): ?InputCommand
    {
        return $this->input === null ? null : new InputCommand($this->input, array_values($prefill));
    }

    public function message(Message $message, array $variables = []): string
    {
        return $this->messages->get($message, $variables);
    }
}
