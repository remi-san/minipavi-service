<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Cursor;

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
     * Any other sequence (a move, an attribute, a clear...), once the cursor has followed it
     * (cursor: a copy of Player's, changing it changes nothing)
     */
    public function sequence(Token $sequence, Cursor $cursor): void;
}
