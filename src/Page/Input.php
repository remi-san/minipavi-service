<?php

declare(strict_types=1);

namespace MiniPaviService\Page;

use MiniPaviService\FunctionKey;
use MiniPaviService\MiniPaviClient;
use MiniPaviService\Page\Display\Position;
use MiniPaviService\Vdt\Screen;

/**
 * Field where the user types, validated with one of the validWith keys
 * (<input> in the page XML format)
 */
final readonly class Input
{
    public int $length;

    /**
     * @param ?int $length defaults to the end of the row
     * @param list<FunctionKey> $validWith keys validating the input
     */
    public function __construct(
        public int $row,
        public int $col = 1,
        ?int $length = null,
        public int $lines = 1,
        public array $validWith = [FunctionKey::Send],
        public bool $cursor = true,
        public string $spaceChar = '.',
    ) {
        Position::check($row, $col);
        $this->length = $length ?? Screen::COLS + 1 - $col;
        if ($this->length < 1 || $col + $this->length - 1 > Screen::COLS) {
            throw new \InvalidArgumentException("Input of length $this->length from column $col goes past column " . Screen::COLS);
        }
        if ($lines < 1 || $row + $lines - 1 > Screen::ROWS) {
            throw new \InvalidArgumentException("Input of $lines lines from row $row goes past row " . Screen::ROWS);
        }
    }

    /**
     * MiniPavi command reading the field.
     *
     * A one-line field is a text input (InputTxt), where every validWith key validates;
     * a multi-line one is a message input (InputMsg), where Suite and Retour move
     * between the lines instead
     *
     * @param string[] $prefill text of the field, one string per line
     * @return array<string, mixed>
     */
    public function toCommand(MiniPaviClient $client, array $prefill = []): array
    {
        if ($this->lines == 1) {
            return $client->createInputTxtCmd($this->col, $this->row, $this->length, FunctionKey::masks(...$this->validWith), $this->cursor, $this->spaceChar, '', (string) ($prefill[0] ?? ''));
        }
        return $client->createInputMsgCmd($this->col, $this->row, $this->length, $this->lines, FunctionKey::masks(...$this->validWith), $this->cursor, $this->spaceChar, $prefill);
    }
}
