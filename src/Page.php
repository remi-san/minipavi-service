<?php

declare(strict_types=1);

namespace MiniPaviService;

use MiniPaviService\Core\Terminal;
use MiniPaviService\Page\InputCommand;

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
    public function display(Terminal $terminal, array $data = []): string;

    /**
     * Command reading the page's input field, null if the page has none
     *
     * @param string[] $prefill text of the field, one string per line
     */
    public function inputCommand(array $prefill = []): ?InputCommand;
}
