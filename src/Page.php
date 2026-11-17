<?php

declare(strict_types=1);

namespace MiniPaviService;

use MiniPaviService\Message\Message;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Page\InputCommand;
use MiniPaviService\Vdt\Vdt;

/**
 * A screen of the service: what it shows on the Minitel, and how it reads what
 * the user types on it.
 */
interface Page
{
    /**
     * Videotex content of the page, for the user's terminal (Request::terminal()), which some
     * elements depend on (e.g. <endkey>)
     *
     * @param array<string, mixed> $data variables of a template
     */
    public function display(Terminal $terminal, array $data = []): Vdt;

    /**
     * Command reading the page's input field, null if the page has none
     *
     * @param string[] $prefill text of the field, one string per line
     */
    public function inputCommand(array $prefill = []): ?InputCommand;

    /**
     * Text of a message for this page: the page's own (<message>), or the service's
     *
     * @param array<string, string|int> $variables values of its {{placeholders}}
     *
     * @throws \LogicException for a message without text, or a placeholder without variable
     */
    public function message(Message $message, array $variables = []): string;
}
