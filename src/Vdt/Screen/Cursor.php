<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

use MiniPaviService\Minitel\ScreenSize;
use MiniPaviService\Vdt\Videotex;

/**
 * Cursor and attributes of a Minitel screen in page mode, as Videotex content moves them: the
 * attributes of the characters, and the zone attributes (Zone)
 */
final class Cursor
{
    private int $row = 1;
    private int $col = 1;
    private Attributes $attributes;
    private Zone $zone;

    public function __construct()
    {
        $this->attributes = Attributes::defaults();
        $this->zone = Zone::none();
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
     * Zone attributes: the zone the last delimiter started, and what waits for the next one
     */
    public function zone(): Zone
    {
        return $this->zone;
    }

    /**
     * Sets the attribute of ESC byte (Attributes::with()), as the Minitel does: ESC M and ESC
     * O received on rows 0 and 1, which can't have double height, are discarded, the size
     * staying as it was, and so are a size and inverse video received in mosaic (MiniPavi's
     * emulator, setSize() and setInvert()). A zone attribute (masking, and underline and
     * background colour in text) waits for a delimiter instead (Zone::waits())
     *
     * @param string $byte the byte after ESC
     */
    public function setAttribute(string $byte): void
    {
        if (!Attributes::allowsDoubleHeight($this->row) && Attributes::isTallSize($byte)) {
            return;
        }
        if ($this->attributes->isMosaic() && !Attributes::appliesInMosaic($byte)) {
            return;
        }
        if (Zone::waits($byte, $this->attributes)) {
            $this->zone = $this->zone->with($byte);
            return;
        }
        $this->attributes = $this->attributes->with($byte);
    }

    /**
     * Sets the character set of SI or SO (Attributes::withCharset()); SO applies the
     * background colour waiting (Zone::attributesAfter())
     *
     * @param string $byte SI (text) or SO (mosaic)
     */
    public function setCharset(string $byte): void
    {
        $this->attributes = $this->zone->attributesAfter($byte, $this->attributes->withCharset($byte));
        $this->zone = $this->zone->withCharset($byte);
    }

    /**
     * Whether drawing bytes now draws a delimiter: a space, in text, while a zone attribute
     * waits (Zone)
     */
    public function delimits(string $bytes): bool
    {
        return $bytes === ' ' && !$this->attributes->isMosaic() && $this->zone->isWaiting();
    }

    /**
     * Draws a delimiter (delimits()): starts the zone of what waited, whose background colour
     * is then the last one applied
     */
    public function delimit(): void
    {
        $this->zone = $this->zone->delimited($this->attributes);
        $this->attributes = $this->attributes->with(Attributes::backgroundColor($this->zone->background()));
    }

    /**
     * Positions the cursor (US) at row, col, as reset() does; a position outside the screen
     * (a row outside 0 to ScreenSize::ROWS, a column outside 1 to ScreenSize::COLS) is
     * ignored, as the Minitel does (MiniPavi's emulator, locate()): the cursor and its
     * attributes stay as they were
     */
    public function position(int $row, int $col): void
    {
        if ($row < 0 || $row > ScreenSize::ROWS || $col < 1 || $col > ScreenSize::COLS) {
            return;
        }
        $this->reset($row, $col);
    }

    /**
     * Positions the cursor (US on the screen, or RS / FF at row 1, column 1), which sets the
     * attributes back to their defaults, and drops the zone attributes
     */
    public function reset(int $row, int $col): void
    {
        $this->row = $row;
        $this->col = $col;
        $this->attributes = Attributes::defaults();
        $this->zone = Zone::none();
    }

    /**
     * Moves right after a character: wraps to the next row of text, as many rows down as the
     * character is tall (Attributes::height()), as the Minitel does
     *
     * @param int $columns the width of the character
     */
    public function advance(int $columns): void
    {
        $this->forward($columns, rows: $this->attributes->height());
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
                $this->left();
                break;
            case Videotex::CURSOR_RIGHT:
                $this->right();
                break;
            case Videotex::CURSOR_DOWN:
                $this->down();
                break;
            case Videotex::CURSOR_UP:
                $this->up();
                break;
            case Videotex::CARRIAGE_RETURN:
                $this->carriageReturn();
                break;
        }
    }

    /**
     * Moves 1 column left (BS), to the last column of the row above from the first one, of
     * the last row from row 1
     */
    public function left(): void
    {
        $this->col--;
        if ($this->col < 1) {
            $this->col = ScreenSize::COLS;
            $this->row = $this->row <= 1 ? ScreenSize::ROWS : $this->row - 1;
        }
    }

    /**
     * Moves 1 column right (HT), 1 row down from the last column, even in double height
     */
    public function right(): void
    {
        $this->forward(1, rows: 1);
    }

    /**
     * Moves 1 row down (LF), to row 1 from the last row
     */
    public function down(): void
    {
        $this->row = $this->row >= ScreenSize::ROWS ? 1 : $this->row + 1;
    }

    /**
     * Moves 1 row up (VT), to the last row from row 1
     */
    public function up(): void
    {
        $this->row = $this->row <= 1 ? ScreenSize::ROWS : $this->row - 1;
    }

    /**
     * Moves to the first column of the row (CR)
     */
    public function carriageReturn(): void
    {
        $this->col = 1;
    }

    /**
     * Moves right by columns, wrapping past the last column rows down, cycling through rows 1
     * to ScreenSize::ROWS (row 0 stays on its last column)
     */
    private function forward(int $columns, int $rows): void
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
