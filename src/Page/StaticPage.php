<?php

declare(strict_types=1);

namespace MiniPaviService\Page;

use MiniPaviService\Core\Terminal;
use MiniPaviService\Message\Message;
use MiniPaviService\Message\Messages;
use MiniPaviService\Page;
use MiniPaviService\Page\Display\Display;
use MiniPaviService\Vdt\VdtHelper;
use MiniPaviService\Vdt\Videotex;

/**
 * Page whose content is always the same: a Display, and optionally an input field
 */
final readonly class StaticPage implements Page
{
    public function __construct(
        private Display $display,
        private Messages $messages,
        private ?Input $input = null,
        private Setup $setup = new Setup(),
    ) {}

    /**
     * Clears the screen, then draws the display; a static page has no variables,
     * data is not used
     *
     * @param array<string, mixed> $data
     */
    public function display(Terminal $terminal, array $data = []): string
    {
        return $this->setup->toVdt() . VdtHelper::clearScreen() . Videotex::CURSOR_OFF . $this->display->toVdt($terminal);
    }

    /**
     * @param string[] $prefill
     */
    public function inputCommand(array $prefill = []): ?InputCommand
    {
        return $this->input?->toCommand($prefill);
    }

    public function message(Message $message, array $variables = []): string
    {
        return $this->messages->get($message, $variables);
    }
}
