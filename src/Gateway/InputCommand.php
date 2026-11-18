<?php

declare(strict_types=1);

namespace MiniPaviService\Gateway;

use MiniPaviService\Minitel\InputField;

/**
 * Command reading an input field, as built by Page::inputCommand(), and sent to the gateway
 * with Response::readInput(): the field, and what it is prefilled with. Each client sends it
 * its own way (MiniPaviClient as MiniPavi's InputTxt or InputMsg, HtmlClient as form fields).
 */
final readonly class InputCommand
{
    /**
     * @param list<string> $prefill text of the field, one string per line
     */
    public function __construct(
        public InputField $field,
        public array $prefill = [],
    ) {}
}
