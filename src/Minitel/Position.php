<?php

declare(strict_types=1);

namespace MiniPaviService\Minitel;

use MiniPaviService\InvalidValueException;

/**
 * Cell of the Minitel screen: a row, 0 (status line) to ScreenSize::ROWS, and a column, 1 to
 * ScreenSize::COLS
 */
final readonly class Position
{
    /**
     * @throws InvalidValueException for a cell off the screen
     */
    public function __construct(
        public int $row,
        public int $col = 1,
    ) {
        if ($row < 0 || $row > ScreenSize::ROWS) {
            throw new InvalidValueException("Row {$row} is off the screen (0 to " . ScreenSize::ROWS . ')');
        }
        if ($col < 1 || $col > ScreenSize::COLS) {
            throw new InvalidValueException("Column {$col} is off the screen (1 to " . ScreenSize::COLS . ')');
        }
    }
}
