<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Cursor;
use MiniPaviService\Vdt\Screen\Zone;

/**
 * Told by Player what Videotex content does on the screen, as it plays it
 *
 * @internal
 */
interface ScreenListener
{
    /**
     * A character drawn at row, col (each repetition of REP too), width cells wide
     */
    public function glyph(int $row, int $col, string $bytes, int $width, Attributes $attributes): void;

    /**
     * A delimiter drawn at row, col, width cells wide: a space starting zone, up to the next
     * delimiter, mosaic or empty cell of its row
     */
    public function delimiter(int $row, int $col, int $width, Attributes $attributes, Zone $zone): void;

    /**
     * CAN at row, col: the row cleared from there to its end with spaces, drawn with these
     * attributes, the first one a delimiter starting zone delimiter, if not null (MiniPavi's
     * emulator prints CAN's spaces, clear()). The cursor stays where it is
     */
    public function clearLine(int $row, int $col, Attributes $attributes, ?Zone $delimiter): void;

    /**
     * Any other sequence (a move, an attribute, a clear...), once the cursor has followed it
     * (cursor: a copy of Player's, changing it changes nothing)
     */
    public function sequence(Token $sequence, Cursor $cursor): void;
}
