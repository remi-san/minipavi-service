<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

/**
 * Cursor and attributes of a Minitel screen in page mode, as Videotex content moves them
 */
final class Cursor
{
    public int $row = 1;
    public int $col = 1;
    /** @var array<string, string> */
    public array $attributes = Attributes::DEFAULTS;

    public function reset(int $row, int $col): void
    {
        $this->row = $row;
        $this->col = $col;
        $this->attributes = Attributes::DEFAULTS;
    }

    // After a character, or a move right: wraps to the next row, from the last one to the first
    public function advance(int $columns): void
    {
        $this->col += $columns;
        if ($this->col > Screen::COLS) {
            if ($this->row == 0) {
                $this->col = Screen::COLS;
                return;
            }
            $this->col = 1;
            $this->row = $this->row == Screen::ROWS ? 1 : $this->row + 1;
        }
    }

    // BS, HT, LF, VT or CR
    public function move(string $byte): void
    {
        switch ($byte) {
            case "\x08":
                $this->col--;
                if ($this->col < 1) {
                    $this->col = Screen::COLS;
                    $this->row = $this->row <= 1 ? Screen::ROWS : $this->row - 1;
                }
                break;
            case "\x09":
                $this->advance(1);
                break;
            case "\x0A":
                $this->row = $this->row >= Screen::ROWS ? 1 : $this->row + 1;
                break;
            case "\x0B":
                $this->row = $this->row <= 1 ? Screen::ROWS : $this->row - 1;
                break;
            case "\x0D":
                $this->col = 1;
                break;
        }
    }
}
