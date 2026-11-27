<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

use MiniPaviService\Minitel\ScreenSize;
use MiniPaviService\Vdt\Screen\Attribute\Attribute;

/**
 * Cursor and attributes of a Minitel screen in page mode, as Videotex content moves them: the
 * attributes of the characters, and the zone attributes (Zone). As the Minitel does
 * (MiniPavi's emulator, decoder.js, locate() and moveCursor()), a positioning onto the status
 * row (row 0) keeps where the cursor was, with its attributes, for leaving the row (LF, or HT
 * from its last column)
 */
final class Cursor
{
    private int $row = 1;
    private int $col = 1;
    private Attributes $attributes;
    private Zone $zone;
    // Where the cursor was, and its attributes, when a positioning brought it onto row 0
    private int $rowBeforeStatusRow = 1;
    private int $colBeforeStatusRow = 1;
    private Attributes $attributesBeforeStatusRow;
    private Zone $zoneBeforeStatusRow;

    public function __construct()
    {
        $this->attributes = Attributes::defaults();
        $this->zone = Zone::none();
        $this->attributesBeforeStatusRow = $this->attributes;
        $this->zoneBeforeStatusRow = $this->zone;
    }

    /**
     * A cursor at row, col, drawing with these attributes: to follow where what is drawn from
     * there goes (advance())
     */
    public static function at(int $row, int $col, Attributes $attributes): self
    {
        $cursor = new self();
        $cursor->row = $row;
        $cursor->col = $col;
        $cursor->attributes = $attributes;

        return $cursor;
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
     * Receives an attribute sent (ESC and its byte), and does with it what the Minitel does:
     * discards one it discards on the cursor's row (Attribute::appliesOn(): a size on row 0,
     * ESC M and ESC O on row 1) or in mosaic (Attribute::appliesInMosaic(): a size and
     * inverse video), what it would set staying as it was (MiniPavi's emulator, setSize() and
     * setInvert()); keeps a zone attribute (masking, and underline and background colour in
     * text) waiting for a delimiter (Attribute::waitsIn()); and applies any other to what the
     * cursor draws next (Attributes::with())
     */
    public function receive(Attribute $attribute): void
    {
        if (!$attribute->appliesOn($this->row)) {
            return;
        }
        if ($this->attributes->isMosaic() && !$attribute->appliesInMosaic()) {
            return;
        }
        if ($attribute->waitsIn($this->attributes)) {
            $this->zone = $attribute->waitingIn($this->zone);
            return;
        }
        $this->attributes = $this->attributes->with($attribute);
    }

    /**
     * Switches to the character set of SI or SO (Attributes::withCharset()), for what the
     * cursor draws next and for the zone; SO applies the background colour waiting
     * (Zone::attributesAfter())
     *
     * @param string $byte SI (text) or SO (mosaic)
     */
    public function switchCharset(string $byte): void
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
        $this->attributes = $this->attributes->withBackground($this->zone->background());
    }

    /**
     * Positions the cursor (US) at row, col, as reset() does; a position outside the screen
     * (a row outside 0 to ScreenSize::ROWS, a column outside 1 to ScreenSize::COLS) is
     * ignored, as the Minitel does (MiniPavi's emulator, locate()): the cursor and its
     * attributes stay as they were. Onto row 0, it keeps where the cursor was, and its
     * attributes, for leaving the row (down()), even from row 0
     */
    public function position(int $row, int $col): void
    {
        if ($row < 0 || $row > ScreenSize::ROWS || $col < 1 || $col > ScreenSize::COLS) {
            return;
        }
        if ($row === 0) {
            $this->rowBeforeStatusRow = $this->row;
            $this->colBeforeStatusRow = $this->col;
            $this->attributesBeforeStatusRow = $this->attributes;
            $this->zoneBeforeStatusRow = $this->zone;
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
     * character is tall (Attributes::height()), as the Minitel does; row 0 stays on its last
     * column
     *
     * @param int $columns the width of the character
     */
    public function advance(int $columns): void
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
        $this->row = (($this->row - 1 + $this->attributes->height()) % ScreenSize::ROWS) + 1;
    }

    /**
     * Moves 1 column left (BS), from the first column to the last one of the row above
     * (up())
     */
    public function left(): void
    {
        $this->col--;
        if ($this->col < 1) {
            $this->col = ScreenSize::COLS;
            $this->up();
        }
    }

    /**
     * Moves 1 column right (HT), from the last column to the first one of the row below
     * (down()), even in double height
     */
    public function right(): void
    {
        $this->col++;
        if ($this->col > ScreenSize::COLS) {
            $this->col = 1;
            $this->down();
        }
    }

    /**
     * Moves 1 row down (LF), to row 1 from the last row; from row 0, back to where the cursor
     * was, with its attributes, when a positioning brought it there (position())
     */
    public function down(): void
    {
        if ($this->row === 0) {
            $this->row = $this->rowBeforeStatusRow;
            $this->col = $this->colBeforeStatusRow;
            $this->attributes = $this->attributesBeforeStatusRow;
            $this->zone = $this->zoneBeforeStatusRow;
            return;
        }
        $this->row = $this->row >= ScreenSize::ROWS ? 1 : $this->row + 1;
    }

    /**
     * Moves 1 row up (VT), to the last row from row 1; ignored on row 0
     */
    public function up(): void
    {
        if ($this->row === 0) {
            return;
        }
        $this->row = $this->row === 1 ? ScreenSize::ROWS : $this->row - 1;
    }

    /**
     * Moves to the first column of the row (CR)
     */
    public function carriageReturn(): void
    {
        $this->col = 1;
    }
}
