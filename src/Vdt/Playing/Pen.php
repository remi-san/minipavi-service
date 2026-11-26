<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Playing;

use MiniPaviService\Vdt\Screen\Cursor;
use MiniPaviService\Vdt\Screen\Zone;

/**
 * Draws at the cursor what the tokens of Videotex content draw, and tells it to a
 * ScreenListener: characters or delimiters (Cursor::delimits()), CAN, FF and the bytes sent as
 * they are. It remembers the last character drawn, which REP repeats: a space after a CAN,
 * whose spaces are drawn last (MiniPavi's emulator, decoder.js: print() remembers each
 * character it prints, those of clear("eol") too). Before the first one, REP repeats the last
 * character the Minitel printed before the content (the gateway's PIN on a service's first
 * page, or the previous page's last character), which can't be known here: each repetition is
 * told as that character (ScreenListener::priorCharacter()).
 *
 * It remembers it as it draws (so it isn't readonly), for one content only: it is only built
 * by Player::play().
 *
 * @internal
 */
final class Pen
{
    // The last character drawn, which REP repeats; null before the first one, when REP
    // repeats the character printed before the content
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
     * REP: draws the last character again, count times; before the first one, the character
     * printed before the content (drawPrior())
     */
    public function repeat(Cursor $cursor, int $count): void
    {
        $last = $this->last;
        for ($n = 0; $n < $count; $n++) {
            if ($last === null) {
                $this->drawPrior($cursor);
                continue;
            }
            $this->draw($cursor, $last);
        }
    }

    /**
     * Draws at the cursor the character printed before the content, which can't be known
     * (ScreenListener::priorCharacter()), and moves past it: as a space would be, a delimiter
     * while a zone attribute waits (Cursor::delimits())
     */
    private function drawPrior(Cursor $cursor): void
    {
        $width = $cursor->attributes()->width();
        $delimiter = $this->spaceStartsZone($cursor);
        $this->listener->priorCharacter($cursor->row(), $cursor->col(), $width, $cursor->attributes(), $delimiter);
        $cursor->advance($width);
    }

    /**
     * CAN at the cursor: clears to the end of its row with spaces, the first one a delimiter
     * while a zone attribute waits (Cursor::delimits()), as when the content draws them. The
     * cursor stays where it is. In double width (or double size), its spaces are two columns
     * wide, so they run on over the next row (two rows down in double size), as text wraps
     * (MiniPavi's emulator, decoder.js, clear("eol"): a space printed for each column from the
     * cursor's to the last): it clears no row to its end, and each space is told as drawn
     * (ClearedSpaces). A space is then the last character drawn, which REP repeats
     */
    public function clearLine(Cursor $cursor): void
    {
        $this->last = ' ';
        $delimiter = $this->spaceStartsZone($cursor);
        $attributes = $cursor->attributes();
        if ($attributes->width() > 1) {
            (new ClearedSpaces($cursor->row(), $cursor->col(), $attributes, $delimiter))->tellTo($this->listener);
            return;
        }
        $this->listener->clearLine($cursor->row(), $cursor->col(), $attributes, $delimiter);
    }

    /**
     * The zone a space drawn at the cursor starts, as a delimiter while a zone attribute waits
     * (Cursor::delimits(), Cursor::delimit()); null if it isn't one
     */
    private function spaceStartsZone(Cursor $cursor): ?Zone
    {
        if (!$cursor->delimits(' ')) {
            return null;
        }
        $cursor->delimit();

        return $cursor->zone();
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
