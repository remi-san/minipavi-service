<?php

declare(strict_types=1);

namespace MiniPaviService\Minitel;

use MiniPaviService\Vdt\Screen\Screen;

/**
 * Cell of the Minitel screen: a row, 0 (status line) to Screen::ROWS, and a column, 1 to
 * Screen::COLS
 */
final readonly class Position
{
    /**
     * @throws \InvalidArgumentException for a cell off the screen
     */
    public function __construct(
        public int $row,
        public int $col = 1,
    ) {
        if ($row < 0 || $row > Screen::ROWS) {
            throw new \InvalidArgumentException("Row {$row} is off the screen (0 to " . Screen::ROWS . ')');
        }
        if ($col < 1 || $col > Screen::COLS) {
            throw new \InvalidArgumentException("Column {$col} is off the screen (1 to " . Screen::COLS . ')');
        }
    }
}
