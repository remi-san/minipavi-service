<?php

declare(strict_types=1);

namespace MiniPaviService;

use MiniPaviService\Page\InputCommand;

/**
 * A screen of the service: what it shows on the Minitel, and how it reads what
 * the user types on it.
 */
interface Page
{
    /**
     * @param array<string, mixed> $data variables of a template
     */
    public function display(array $data = []): string;

    /**
     * Command reading the page's input field, null if the page has none
     *
     * @param string[] $prefill text of the field, one string per line
     */
    public function inputCommand(array $prefill = []): ?InputCommand;
}
