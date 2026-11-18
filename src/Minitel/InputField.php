<?php

declare(strict_types=1);

namespace MiniPaviService\Minitel;

use MiniPaviService\Vdt\Screen\Screen;

/**
 * Field of the Minitel screen where the user types, validated with one of the validWith keys
 * (<input> in the page XML format). One line is a text field, where every validWith key
 * validates; several lines are a message, where Suite and Retour move between the lines and
 * the other validWith keys validate.
 */
final readonly class InputField
{
    public int $length;

    /**
     * @param int $row first row of the field, 0 (status line) to Screen::ROWS
     * @param int $col first column, 1 to Screen::COLS
     * @param ?int $length columns of each line; defaults to the end of the row
     * @param list<FunctionKey> $validWith keys validating the field
     * @param string $spaceChar shown in the empty cells of the field
     *
     * @throws \InvalidArgumentException for a field off the screen
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
        if ($row < 0 || $row > Screen::ROWS) {
            throw new \InvalidArgumentException("Row {$row} is off the screen (0 to " . Screen::ROWS . ')');
        }
        if ($col < 1 || $col > Screen::COLS) {
            throw new \InvalidArgumentException("Column {$col} is off the screen (1 to " . Screen::COLS . ')');
        }
        $this->length = $length ?? (Screen::COLS + 1 - $col);
        if ($this->length < 1 || ($col + $this->length - 1) > Screen::COLS) {
            throw new \InvalidArgumentException(
                "Input of length {$this->length} from column {$col} goes past column " . Screen::COLS,
            );
        }
        if ($lines < 1 || ($row + $lines - 1) > Screen::ROWS) {
            throw new \InvalidArgumentException(
                "Input of {$lines} lines from row {$row} goes past row " . Screen::ROWS,
            );
        }
    }

    /**
     * Whether it is a message, of several lines, rather than a text field
     */
    public function isMessage(): bool
    {
        return $this->lines > 1;
    }

    /**
     * Whether the key validates the field
     */
    public function isValidatedWith(FunctionKey $key): bool
    {
        return \in_array($key, $this->validWith, strict: true);
    }
}
