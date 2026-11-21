<?php

declare(strict_types=1);

namespace MiniPaviService\Gateway;

use MiniPaviService\Minitel\InputField;

/**
 * Command reading an input field, as built by Page::inputCommand(), and sent to the gateway
 * with RunCommand::readInput(): the field, and what it is prefilled with. Each client sends it
 * its own way (MiniPaviClient, through MiniPaviCliGateway, as MiniPavi's InputTxt or
 * InputMsg, HtmlClient as form fields).
 */
final readonly class InputCommand implements Command
{
    /**
     * @param list<string> $prefill text of the field, one string per line
     */
    public function __construct(
        public InputField $field,
        public array $prefill = [],
    ) {}

    public function sendWith(CommandSender $sender): mixed
    {
        return $sender->readInput($this);
    }
}
