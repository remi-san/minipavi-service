<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

use MiniPaviService\Vdt\Screen\Attributes;
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
     * delimiter, mosaic or empty cell of its row, and of the row above when it is tall
     * (Attributes::height(): Screen\Zones)
     */
    public function delimiter(int $row, int $col, int $width, Attributes $attributes, Zone $zone): void;

    /**
     * CAN at row, col: a space drawn with these attributes in each column from there to the
     * last, the first one a delimiter starting zone delimiter, if not null (MiniPavi's
     * emulator prints CAN's spaces, clear()): the row cleared to its end, and in double
     * height the row above too. The cursor stays where it is. A CAN in double width (or
     * double size) isn't told so: its spaces, two columns wide, run on over the next row, and
     * each is told as drawn (glyph(), delimiter(): Pen::clearLine())
     */
    public function clearLine(int $row, int $col, Attributes $attributes, ?Zone $delimiter): void;

    /**
     * FF: the screen cleared but row 0, the status line, which stays; the cursor home
     */
    public function clear(): void;

    /**
     * Bytes sent as they are, which don't change what the screen shows here (protocol,
     * cursor on or off, bell, SEP or SS3 and their byte, an escape sequence other than an
     * attribute...)
     */
    public function raw(string $bytes): void;
}
