<?php

declare(strict_types=1);

namespace MiniPaviService\Gateway;

use MiniPaviService\Minitel\FunctionKey;

/**
 * Command reading an input field, as built by Page::inputCommand(), and sent to the gateway
 * with Response::readInput(): where the field is, its size, the keys validating it, and what
 * it is prefilled with. Each client sends it its own way (MiniPaviClient as MiniPavi's InputTxt
 * or InputMsg, HtmlClient as form fields).
 */
final readonly class InputCommand
{
    /**
     * @param int $width columns of each line
     * @param list<FunctionKey> $validWith keys validating the field
     * @param string $spaceChar shown in the empty cells of the field
     * @param list<string> $prefill text of the field, one string per line
     */
    private function __construct(
        public InputKind $kind,
        public int $col,
        public int $row,
        public int $width,
        public int $lines,
        public array $validWith,
        public bool $cursor,
        public string $spaceChar,
        public array $prefill,
    ) {}

    /**
     * Command reading a one-line text field, where every validWith key validates
     *
     * @param list<FunctionKey> $validWith keys validating the field
     */
    public static function text(
        int $col,
        int $row,
        int $length,
        array $validWith,
        bool $cursor = true,
        string $spaceChar = '.',
        string $prefill = '',
    ): self {
        return new self(InputKind::Text, $col, $row, $length, 1, $validWith, $cursor, $spaceChar, [$prefill]);
    }

    /**
     * Command reading a message of several lines, where Suite and Retour move between the
     * lines and the other validWith keys validate
     *
     * @param list<FunctionKey> $validWith keys validating the field
     * @param list<string> $prefill text of the field, one string per line
     */
    public static function message(
        int $col,
        int $row,
        int $width,
        int $height,
        array $validWith,
        bool $cursor = true,
        string $spaceChar = '.',
        array $prefill = [],
    ): self {
        return new self(InputKind::Message, $col, $row, $width, $height, $validWith, $cursor, $spaceChar, $prefill);
    }

    /**
     * Whether the key validates the field
     */
    public function isValidatedWith(FunctionKey $key): bool
    {
        return \in_array($key, $this->validWith, strict: true);
    }
}
