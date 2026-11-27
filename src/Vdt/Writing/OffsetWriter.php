<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Writing;

use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\ScreenSize;
use MiniPaviService\Vdt\Playing\ClearedSpaces;
use MiniPaviService\Vdt\Playing\Player;
use MiniPaviService\Vdt\Playing\ScreenListener;
use MiniPaviService\Vdt\Playing\StandInSpace;
use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\ShownScreen;
use MiniPaviService\Vdt\Screen\ShownScreens;
use MiniPaviService\Vdt\Screen\Zone;
use MiniPaviService\Vdt\Screen\Zones;
use MiniPaviService\Vdt\Vdt;

/**
 * Writes again (Writer) what content draws, moved by x columns right and y rows down: what
 * Offset listens to while playing the content. Characters that end up off the screen are
 * dropped, and row 0, the status line, is not moved. The zones of the moved rows are the
 * content's, as far as they can be: as each screen shows once drawn (ShownScreen), up to the
 * clear screen that erases it, or the end of the content.
 *
 * On a row moved left, the zone the columns going off the screen leave starts again at a
 * delimiter written in the first text space it shows in, in the right half of a
 * double-width character left in column 1, or in the top half of a tall one left alone on
 * the last row, each written as a space (moved(), ShownScreen::restart(), #170, #173), as
 * what is drawn there isn't written: what is before that
 * space has no column left for one, and loses it. That space may be the top half of a tall
 * space drawn from the row below: the delimiter is written at normal height over it, once
 * that space is written. A delimiter something is drawn over later is written as a space, as
 * its zone doesn't last. Each text cell is written with the zone the cells written before it
 * leave (zoneBefore()), so that the Writer starts no other.
 *
 * The character a REP repeats before any is drawn, printed before the content, can't be known
 * (ScreenListener::priorCharacter()): it is written with REP too, which repeats it as long as
 * nothing is printed before. It is moved as the space that stands in for it (moved()): one
 * column wide where only its right half stays in column 1, or where only its left half shows
 * in column 40, moved left.
 *
 * Its Writer keeps where it is and what it wrote (so it isn't readonly), so it listens to one
 * content only: it is only built, and played to, by write().
 *
 * @internal
 */
final class OffsetWriter implements ScreenListener
{
    private readonly Writer $out;
    // The zones of what is written, where the content draws it (not moved)
    private readonly Zones $zones;

    /**
     * @param ShownScreen $shown the screen being drawn, as it shows once drawn (not moved): up
     *                           to the clear screen that erases it, or the end of the content
     */
    private function __construct(
        private ShownScreen $shown,
        private readonly int $x,
        private readonly int $y,
    ) {
        $this->out = new Writer();
        $this->zones = new Zones();
    }

    /**
     * The content, moved
     */
    public static function write(Vdt $vdt, int $x, int $y): Vdt
    {
        $writer = new self(ShownScreens::of($vdt), $x, $y);
        (new Player())->play($vdt, $writer);

        return new Vdt($writer->out->vdt());
    }

    public function glyph(Position $position, string $bytes, Attributes $attributes): void
    {
        $restart = $this->restartAt($position->row, $position, $bytes, $attributes);
        $tall = $attributes->height() > 1;
        if ($restart !== null && (!$tall || $this->shown->showsOnTop($position, $restart))) {
            // A tall one starts the zone again on the row above too, where it shows the same
            $this->writeDelimiter($position, $attributes, $restart);
            return;
        }
        $this->writeMoved($position, $bytes, $attributes);
        if ($restart !== null) {
            // A tall space whose top half shows another zone, which a tall delimiter would
            // change: a delimiter at normal height over its bottom half
            $this->writeDelimiter($position, $attributes->flattened(), $restart);
        }
        $above = $tall ? $this->restartAt($position->row - 1, $position, $bytes, $attributes) : null;
        if ($above !== null) {
            // The zone of the row above starts again in its top half: a delimiter at normal
            // height over it
            $this->delimiterOnTop(new Position($position->row - 1, $position->col), $attributes->flattened(), $above);
        }
    }

    /**
     * Writes a delimiter starting zone at position, moved, at normal height (attributes), over
     * the top half of the tall space drawn in its column on the row below, once that space is
     * written
     */
    private function delimiterOnTop(Position $position, Attributes $attributes, Zone $zone): void
    {
        $this->zones->delimiter($position, $attributes, $zone);
        $moved = $this->moved($position, ' ', $attributes);
        if ($moved !== null) {
            $this->out->delimiter($moved->position, $moved->attributes, $zone);
        }
    }

    // Writes a character drawn at position, moved, in the zone the cells written before it leave
    private function writeMoved(Position $position, string $bytes, Attributes $attributes): void
    {
        $zone = $this->zoneBefore($this->asShown($position, $bytes, $attributes)->position->row, $position->col);
        $this->zones->draw($position, $attributes);
        $moved = $this->moved($position, $bytes, $attributes);
        if ($moved !== null) {
            $this->out->glyph($moved->position, $moved->bytes, $moved->attributes, $zone);
        }
    }

    public function delimiter(Position $position, Attributes $attributes, Zone $zone): void
    {
        if (!$this->shown->shows($position, $attributes)) {
            // Drawn over: its zone doesn't last
            $this->glyph($position, ' ', $attributes);
            return;
        }
        $this->writeDelimiter($position, $attributes, $zone);
    }

    /**
     * Writes a delimiter starting zone at position (not moved), moved: a space, one column
     * wide in column 1 for a character whose right half only stays (moved()). Where the zone
     * starts again (restartAt()), what is drawn there shows once drawn, so it is written
     */
    private function writeDelimiter(Position $position, Attributes $attributes, Zone $zone): void
    {
        $this->zones->delimiter($position, $attributes, $zone);
        $moved = $this->moved($position, ' ', $attributes);
        if ($moved !== null) {
            $this->out->delimiter($moved->position, $moved->attributes, $zone);
        }
    }

    /**
     * The character printed before the content, written with REP (Writer::priorCharacter()),
     * moved as the space that stands in for it (StandInSpace, moved()). Where the zone of the
     * columns going off the screen starts again (restartAt()), that space is a delimiter of
     * that zone: its attributes are written just before it, which wait, as in the content, if
     * that character isn't a space
     */
    public function priorCharacter(Position $position, Attributes $attributes, ?Zone $delimiter): void
    {
        $delimiter ??= $this->restartAt($position->row, $position, ' ', $attributes);
        (new StandInSpace($position, $attributes, $delimiter))->drawIn($this->zones);
        $moved = $this->moved($position, ' ', $attributes);
        if ($moved === null) {
            return;
        }
        $this->out->priorCharacter($moved->position, $moved->attributes, $delimiter);
    }

    public function clear(): void
    {
        $this->shown = $this->shown->cleared();
        $this->zones->clear();
        $this->out->clear();
    }

    public function raw(string $bytes): void
    {
        $this->out->raw($bytes);
    }

    /**
     * Where a character drawn at position goes, and the bytes and attributes it is written
     * with; null if off the screen. Row 0 stays where it is; the other rows stay within rows 1 to ROWS, the
     * character written with a size the Minitel accepts on the row it is moved to (no ESC M or
     * ESC O on row 1). A double-width one moved into the last column is written there: the
     * Minitel shows its left half (MiniPavi's emulator, decoder.js, printG0Char()). One moved
     * left so that only its right half stays can't be shown (a character is drawn from its
     * left half, and the Minitel ignores a positioning in column 0): a space is written in
     * column 1 instead, one column wide (Attributes::narrowed()), with its attributes, which
     * shows a space's right half as it is, and another character's background (or colour,
     * inverted) without its glyph. A space drawn in the last column, of which the Minitel
     * shows the left half only, moved left is written one column wide too: what it shows of
     * it. Another character there is written whole. The top half of a tall one left alone on
     * the last row, its bottom row moved off the screen, is written there as a space at
     * normal height (asShown()), with its attributes, which shows the same way.
     *
     * The row and column are moved as ints: moved, they may be off the screen, which a
     * Position isn't
     */
    private function moved(Position $position, string $bytes, Attributes $attributes): ?WrittenCharacter
    {
        $row = $position->row;
        $col = $position->col;
        if ($row !== 0) {
            $shown = $this->asShown($position, $bytes, $attributes);
            $bytes = $shown->bytes;
            $attributes = $shown->attributes;
            if ($bytes === ' ' && $this->x < 0 && ($col + $attributes->width() - 1) > ScreenSize::COLS) {
                $attributes = $attributes->narrowed();
            }
            $row = $shown->position->row + $this->y;
            $col += $this->x;
            if ($row < 1 || $row > ScreenSize::ROWS) {
                return null;
            }
            $attributes = $attributes->writableOn($row);
        }
        if ($col < 1 && ($col + $attributes->width()) > 1) {
            return new WrittenCharacter(new Position($row, 1), ' ', $attributes->narrowed());
        }
        if ($col < 1 || $col > ScreenSize::COLS) {
            return null;
        }

        return new WrittenCharacter(new Position($row, $col), $bytes, $attributes);
    }

    /**
     * What of a character drawn at position (not moved) with these bytes and attributes shows
     * once moved: the position (not moved) it shows at, and the bytes and attributes it is
     * written with there. It is the character as it is, unless it is a tall one whose bottom row is moved
     * off the screen, below the last row, leaving its top half alone on it: the Minitel draws
     * a tall character from its bottom row (MiniPavi's emulator, decoder.js, printG0Char()),
     * so that top half is written on the row above as a space at normal height, with its
     * attributes (#173)
     */
    private function asShown(Position $position, string $bytes, Attributes $attributes): WrittenCharacter
    {
        // Row 0, the status row, not moved, has no tall character (Attributes::allowsDoubleHeight())
        if (($position->row + $this->y) !== (ScreenSize::ROWS + 1) || $attributes->height() === 1) {
            return new WrittenCharacter($position, $bytes, $attributes);
        }

        return new WrittenCharacter(new Position($position->row - 1, $position->col), ' ', $attributes->flattened());
    }

    /**
     * CAN, moved: the cleared cells of the moved row stop at the screen's last column. Moved
     * left, they must stop before the columns that came from beyond the screen: its spaces
     * are written one by one instead, each told to this writer as drawn (ClearedSpaces): the
     * first a delimiter (delimiter(): written while it shows), the others as any space
     * (glyph(): one where the zone of the columns gone off the screen starts again a
     * delimiter, a tall one's top half in the zone of the row above)
     */
    public function clearLine(Position $position, Attributes $attributes, ?Zone $delimiter): void
    {
        $row = $position->row;
        $col = $position->col;
        if ($row !== 0 && $this->x < 0) {
            (new ClearedSpaces($position, $attributes, $delimiter))->tellTo($this);
            return;
        }
        // A delimiter drawn over, on its row and in double height the row above: its zone
        // doesn't last, nor is it written
        $delimiter = $this->shown->shows($position, $attributes) ? $delimiter : null;
        // The top halves alone on the last row at normal height (asShown())
        $shown = $this->asShown($position, ' ', $attributes);
        $zone = $this->zoneBefore($shown->position->row, $col);
        $this->zones->clearLine($position, $attributes, $delimiter);
        if ($row === 0) {
            $this->out->clearLine($position, $attributes, $zone, $delimiter);
            return;
        }
        $movedRow = $shown->position->row + $this->y;
        if ($movedRow < 1 || $movedRow > ScreenSize::ROWS) {
            return;
        }
        if (($col + $this->x) <= ScreenSize::COLS) {
            $this->out->clearLine(new Position($movedRow, $col + $this->x), $shown->attributes, $zone, $delimiter);
        }
    }

    /**
     * The zone of row restarted (not moved) a delimiter written for bytes drawn at position
     * (not moved) starts again, if the zone the columns going off the screen leave starts
     * again there (ShownScreen::restart()): a text cell written as a space (moved() of the
     * character drawn at position: a space, a character whose right half only stays in
     * column 1, #170, or the top half of a tall one alone on the last row, #173), on a moved
     * row (restarted is its row, or the row above for the top half of a tall one)
     */
    private function restartAt(int $restarted, Position $position, string $bytes, Attributes $attributes): ?Zone
    {
        if ($restarted === 0 || $attributes->isMosaic()) {
            return null;
        }
        if ($this->moved($position, $bytes, $attributes)?->bytes !== ' ') {
            return null;
        }
        $restart = $this->shown->restart($restarted, 1 - $this->x, ScreenSize::ROWS - $this->y);
        if ($restart === null || !$restart->isAt($position)) {
            return null;
        }

        return $restart->zone;
    }

    /**
     * The zone a text cell drawn at row, col (not moved) is in, as written: what the cells
     * written before it on its moved row leave, from the column moved to column 1 (moved
     * right, before column 1: the columns from it to column 1, empty, change no zone). The
     * columns it reads from may be off the screen, so it takes ints
     */
    private function zoneBefore(int $row, int $col): Zone
    {
        if ($row === 0) {
            // Not moved
            return $this->zones->before($row, $col);
        }

        return $this->zones->beforeFrom(Zone::none(), $row, 1 - $this->x, $col);
    }
}
