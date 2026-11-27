<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Playing;

use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\ScreenSize;
use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Cursor;
use MiniPaviService\Vdt\Screen\Zone;

/**
 * The spaces of a CAN at a position, as MiniPavi's emulator prints them (decoder.js,
 * clear("eol")): one with its attributes for each column from its own to the last, each where
 * the one before leaves the cursor (Cursor::advance()), the first one a delimiter while a zone
 * attribute waits. At normal width, they clear the rest of the row; in double width (or double
 * size), they are two columns wide, so they run on over the next row (two rows down in double
 * size), as text wraps. Immutable.
 *
 * @internal
 */
final readonly class ClearedSpaces
{
    /**
     * @param ?Zone $delimiter the zone the first space starts as a delimiter; null if it isn't one
     */
    public function __construct(
        private Position $position,
        private Attributes $attributes,
        private ?Zone $delimiter,
    ) {}

    /**
     * Tells each space to listener as drawn: the first one a delimiter, if it is one
     * (ScreenListener::delimiter()), the others as characters (ScreenListener::glyph())
     */
    public function tellTo(ScreenListener $listener): void
    {
        $width = $this->attributes->width();
        $spaces = Cursor::at($this->position->row, $this->position->col, $this->attributes);
        $first = $this->position->col;
        if ($this->delimiter !== null) {
            $listener->delimiter($this->position, $this->attributes, $this->delimiter);
            $spaces->advance($width);
            $first++;
        }
        for ($space = $first; $space <= ScreenSize::COLS; $space++) {
            $listener->glyph(new Position($spaces->row(), $spaces->col()), ' ', $this->attributes);
            $spaces->advance($width);
        }
    }
}
