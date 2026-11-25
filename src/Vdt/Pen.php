<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

use MiniPaviService\Vdt\Screen\Cursor;

/**
 * Draws at the cursor what the tokens of Videotex content draw, and tells it to a
 * ScreenListener: characters or delimiters (Cursor::delimits()), CAN, FF and the bytes sent as
 * they are. It remembers the last character drawn, which REP repeats.
 *
 * It remembers it as it draws (so it isn't readonly), for one content only: it is only built
 * by Player::play().
 *
 * @internal
 */
final class Pen
{
    // The last character drawn, which REP repeats; null before the first one
    private ?string $last = null;

    public function __construct(
        private readonly ScreenListener $listener,
    ) {}

    /**
     * Draws a character at the cursor, or a delimiter (Cursor::delimits()), and moves past it
     */
    public function draw(Cursor $cursor, string $bytes): void
    {
        $this->last = $bytes;
        $width = $cursor->attributes()->width();
        if (!$cursor->delimits($bytes)) {
            $this->listener->glyph($cursor->row(), $cursor->col(), $bytes, $width, $cursor->attributes());
            $cursor->advance($width);
            return;
        }
        $cursor->delimit();
        $this->listener->delimiter($cursor->row(), $cursor->col(), $width, $cursor->attributes(), $cursor->zone());
        $cursor->advance($width);
    }

    /**
     * REP: draws the last character again, count times; nothing, nor told, with no character
     * before
     */
    public function repeat(Cursor $cursor, int $count): void
    {
        $last = $this->last;
        if ($last === null) {
            return;
        }
        for ($n = 0; $n < $count; $n++) {
            $this->draw($cursor, $last);
        }
    }

    /**
     * CAN at the cursor: clears to the end of its row with spaces, the first one a delimiter
     * while a zone attribute waits (Cursor::delimits()), as when the content draws them. The
     * cursor stays where it is. In double width (or double size), its spaces are two columns
     * wide, so they run on over the next row (two rows down in double size), as text wraps
     * (MiniPavi's emulator, decoder.js, clear("eol"): a space printed for each column from the
     * cursor's to the last): it clears no row to its end, and each space is told as drawn
     * (ClearedSpaces)
     */
    public function clearLine(Cursor $cursor): void
    {
        $delimiter = null;
        if ($cursor->delimits(' ')) {
            $cursor->delimit();
            $delimiter = $cursor->zone();
        }
        $attributes = $cursor->attributes();
        if ($attributes->width() > 1) {
            (new ClearedSpaces($cursor->row(), $cursor->col(), $attributes, $delimiter))->tellTo($this->listener);
            return;
        }
        $this->listener->clearLine($cursor->row(), $cursor->col(), $attributes, $delimiter);
    }

    /**
     * FF: the screen cleared (ScreenListener::clear())
     */
    public function clearScreen(): void
    {
        $this->listener->clear();
    }

    /**
     * Bytes sent as they are (ScreenListener::raw())
     */
    public function raw(string $bytes): void
    {
        $this->listener->raw($bytes);
    }
}
