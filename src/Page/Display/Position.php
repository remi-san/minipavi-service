<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Display;

use MiniPaviService\Vdt\Screen\Screen;

/**
 * Bounds of the screen (Screen::ROWS and Screen::COLS), shared by the elements
 */
final class Position
{
    /**
     * Throws if row (0 to 24, 0 = status line) or col (1 to 40) is off the screen
     */
    public static function check(int $row, int $col): void
    {
        if ($row < 0 || $row > Screen::ROWS) {
            throw new \InvalidArgumentException("Row {$row} is off the screen (0 to " . Screen::ROWS . ')');
        }
        if ($col < 1 || $col > Screen::COLS) {
            throw new \InvalidArgumentException("Column {$col} is off the screen (1 to " . Screen::COLS . ')');
        }
    }
}
