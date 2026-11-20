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
     * while a zone attribute waits (Cursor::delimits()), as when the content draws them
     */
    public function clearLine(Cursor $cursor): void
    {
        $delimiter = null;
        if ($cursor->delimits(' ')) {
            $cursor->delimit();
            $delimiter = $cursor->zone();
        }
        $this->listener->clearLine($cursor->row(), $cursor->col(), $cursor->attributes(), $delimiter);
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
