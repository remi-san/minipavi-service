<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

use MiniPaviService\Minitel\ScreenSize;
use MiniPaviService\Vdt\Videotex;

/**
 * Cursor and attributes of a Minitel screen in page mode, as Videotex content moves them
 */
final class Cursor
{
    private int $row = 1;
    private int $col = 1;
    private Attributes $attributes;

    public function __construct()
    {
        $this->attributes = Attributes::defaults();
    }

    /**
     * Row of the cursor: 0 (the status row) to ScreenSize::ROWS
     */
    public function row(): int
    {
        return $this->row;
    }

    /**
     * Column of the cursor, from 1
     */
    public function col(): int
    {
        return $this->col;
    }

    /**
     * Attributes of what the cursor draws next
     */
    public function attributes(): Attributes
    {
        return $this->attributes;
    }

    /**
     * Sets the attribute of ESC byte (Attributes::with()), as the Minitel does: ESC M and ESC
     * O received on rows 0 and 1, which can't have double height, are discarded, the size
     * staying as it was, and so is any size received in mosaic (MiniPavi's emulator)
     *
     * @param string $byte the byte after ESC
     */
    public function setAttribute(string $byte): void
    {
        if (!Attributes::allowsDoubleHeight($this->row) && Attributes::isTallSize($byte)) {
            return;
        }
        if ($this->attributes->isMosaic() && Attributes::isSize($byte)) {
            return;
        }
        $this->attributes = $this->attributes->with($byte);
    }

    /**
     * Sets the character set of SI or SO (Attributes::withCharset())
     *
     * @param string $byte SI (text) or SO (mosaic)
     */
    public function setCharset(string $byte): void
    {
        $this->attributes = $this->attributes->withCharset($byte);
    }

    /**
     * Positions the cursor (US, or RS / FF at row 1, column 1), which sets the attributes back
     * to their defaults
     */
    public function reset(int $row, int $col): void
    {
        $this->row = $row;
        $this->col = $col;
        $this->attributes = Attributes::defaults();
    }

    /**
     * Moves right after a character: wraps to the next row of text, as many rows down as the
     * character is tall (Attributes::height()), as the Minitel does
     *
     * @param int $columns the width of the character
     */
    public function advance(int $columns): void
    {
        $this->right($columns, rows: $this->attributes->height());
    }

    /**
     * Follows a cursor move: BS, HT, LF, VT or CR (Videotex::CURSOR_*, CARRIAGE_RETURN)
     *
     * @param string $byte the code of the move
     */
    public function move(string $byte): void
    {
        switch ($byte) {
            case Videotex::CURSOR_LEFT:
                $this->col--;
                if ($this->col < 1) {
                    $this->col = ScreenSize::COLS;
                    $this->row = $this->row <= 1 ? ScreenSize::ROWS : $this->row - 1;
                }
                break;
            case Videotex::CURSOR_RIGHT:
                // 1 row down from the last column, even in double height
                $this->right(1, rows: 1);
                break;
            case Videotex::CURSOR_DOWN:
                $this->row = $this->row >= ScreenSize::ROWS ? 1 : $this->row + 1;
                break;
            case Videotex::CURSOR_UP:
                $this->row = $this->row <= 1 ? ScreenSize::ROWS : $this->row - 1;
                break;
            case Videotex::CARRIAGE_RETURN:
                $this->col = 1;
                break;
        }
    }

    /**
     * Moves right by columns, wrapping past the last column rows down, cycling through rows 1
     * to ScreenSize::ROWS (row 0 stays on its last column)
     */
    private function right(int $columns, int $rows): void
    {
        $this->col += $columns;
        if ($this->col <= ScreenSize::COLS) {
            return;
        }
        if ($this->row === 0) {
            $this->col = ScreenSize::COLS;
            return;
        }
        $this->col = 1;
        $this->row = (($this->row - 1 + $rows) % ScreenSize::ROWS) + 1;
    }
}
