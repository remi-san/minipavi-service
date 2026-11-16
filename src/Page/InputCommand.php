<?php

declare(strict_types=1);

namespace MiniPaviService\Page;

use MiniPavi\MiniPaviCli;

/**
 * MiniPavi command reading an input field (InputTxt or InputMsg), as built by
 * Page::inputCommand(), and sent to the gateway with Response::readInput()
 */
final readonly class InputCommand
{
    /**
     * @param array<string, mixed> $command the command, as MiniPaviCli builds it
     */
    public function __construct(
        private array $command,
    ) {}

    /**
     * Command reading a one-line text field, where every validWith key validates
     *
     * @param int $validWith mask of the keys validating the field (FunctionKey::masks())
     */
    public static function text(int $col, int $row, int $length, int $validWith, bool $cursor = true, string $spaceChar = '.', string $prefill = ''): self
    {
        return new self(MiniPaviCli::createInputTxtCmd($col, $row, $length, $validWith, $cursor, $spaceChar, '', $prefill));
    }

    /**
     * Command reading a message of several lines, where Suite and Retour move between the
     * lines and the other validWith keys validate
     *
     * @param int $validWith mask of the keys validating the field (FunctionKey::masks())
     * @param string[] $prefill text of the field, one string per line
     */
    public static function message(int $col, int $row, int $width, int $height, int $validWith, bool $cursor = true, string $spaceChar = '.', array $prefill = []): self
    {
        return new self(MiniPaviCli::createInputMsgCmd($col, $row, $width, $height, $validWith, $cursor, $spaceChar, $prefill));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->command;
    }
}
