<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

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
     * Row of the cursor: 0 (the status row) to Screen::ROWS
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
     * Sets the attribute of ESC byte (Attributes::with())
     */
    public function setAttribute(string $byte): void
    {
        $this->attributes = $this->attributes->with($byte);
    }

    /**
     * Sets the character set of SI or SO (Attributes::withCharset())
     */
    public function setCharset(string $byte): void
    {
        $this->attributes = $this->attributes->withCharset($byte);
    }

    public function reset(int $row, int $col): void
    {
        $this->row = $row;
        $this->col = $col;
        $this->attributes = Attributes::defaults();
    }

    // After a character, or a move right: wraps to the next row, from the last one to the first
    public function advance(int $columns): void
    {
        $this->col += $columns;
        if ($this->col > Screen::COLS) {
            if ($this->row === 0) {
                $this->col = Screen::COLS;
                return;
            }
            $this->col = 1;
            $this->row = $this->row === Screen::ROWS ? 1 : $this->row + 1;
        }
    }

    // BS, HT, LF, VT or CR
    public function move(string $byte): void
    {
        switch ($byte) {
            case Videotex::CURSOR_LEFT:
                $this->col--;
                if ($this->col < 1) {
                    $this->col = Screen::COLS;
                    $this->row = $this->row <= 1 ? Screen::ROWS : $this->row - 1;
                }
                break;
            case Videotex::CURSOR_RIGHT:
                $this->advance(1);
                break;
            case Videotex::CURSOR_DOWN:
                $this->row = $this->row >= Screen::ROWS ? 1 : $this->row + 1;
                break;
            case Videotex::CURSOR_UP:
                $this->row = $this->row <= 1 ? Screen::ROWS : $this->row - 1;
                break;
            case Videotex::CARRIAGE_RETURN:
                $this->col = 1;
                break;
        }
    }
}
