<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

use MiniPaviService\Minitel\ScreenSize;
use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\ShownScreen;
use MiniPaviService\Vdt\Screen\ShownScreens;
use MiniPaviService\Vdt\Screen\Zone;
use MiniPaviService\Vdt\Screen\Zones;

/**
 * Writes again (Writer) what content draws, moved by x columns right and y rows down: what
 * Offset listens to while playing the content. Characters that end up off the screen are
 * dropped, and row 0, the status line, is not moved. The zones of the moved rows are the
 * content's, as far as they can be: as each screen shows once drawn (ShownScreen), up to the
 * clear screen that erases it, or the end of the content.
 *
 * On a row moved left, the zone the columns going off the screen leave starts again at a
 * delimiter written in the first text space it shows in, or in the right half of a
 * double-width character left in column 1, which is written as a space (moved(),
 * ShownScreen::restart(), #170), as what is drawn there isn't written: what is before that
 * space has no column left for one, and loses it. That space may be the top half of a tall space drawn from the row below: the delimiter
 * is written at normal height over it, once that space is written. A delimiter something is
 * drawn over later is written as a space, as its zone doesn't last. Each text cell is written
 * with the zone the cells written before it leave (zoneBefore()), so that the Writer starts
 * no other.
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

    public function glyph(int $row, int $col, string $bytes, int $width, Attributes $attributes): void
    {
        $restart = $this->restartAt($row, $row, $col, $bytes, $attributes);
        $tall = $attributes->height() > 1;
        if ($restart !== null && (!$tall || $this->shown->showsOnTop($row, $col, $restart))) {
            // A tall one starts the zone again on the row above too, where it shows the same
            $this->writeDelimiter($row, $col, $width, $attributes, $restart);
            return;
        }
        $this->writeMoved($row, $col, $bytes, $width, $attributes);
        if ($restart !== null) {
            // A tall space whose top half shows another zone, which a tall delimiter would
            // change: a delimiter at normal height over its bottom half
            $this->writeDelimiter($row, $col, $width, $attributes->flattened(), $restart);
        }
        $above = $tall ? $this->restartAt($row - 1, $row, $col, $bytes, $attributes) : null;
        if ($above !== null) {
            // The zone of the row above starts again in its top half: a delimiter at normal
            // height over it
            $this->delimiterOnTop($row - 1, $col, $width, $attributes->flattened(), $above);
        }
    }

    /**
     * Writes a delimiter starting zone at row, col, moved, at normal height, over the top half
     * of the tall space drawn at col on the row below, once that space is written
     */
    private function delimiterOnTop(int $row, int $col, int $width, Attributes $attributes, Zone $zone): void
    {
        $this->zones->delimiter($row, $col, $width, height: 1, zone: $zone);
        $moved = $this->moved($row, $col, ' ', $attributes);
        if ($moved !== null) {
            [$row, $col, , $attributes] = $moved;
            $this->out->delimiter($row, $col, $attributes->width(), $attributes, $zone);
        }
    }

    // Writes a character drawn at row, col, moved, in the zone the cells written before it leave
    private function writeMoved(int $row, int $col, string $bytes, int $width, Attributes $attributes): void
    {
        $zone = $this->zoneBefore($row, $col);
        $this->zones->draw($row, $col, $width, $attributes);
        $moved = $this->moved($row, $col, $bytes, $attributes);
        if ($moved !== null) {
            [$row, $col, $bytes, $attributes] = $moved;
            $this->out->glyph($row, $col, $bytes, $attributes->width(), $attributes, $zone);
        }
    }

    public function delimiter(int $row, int $col, int $width, Attributes $attributes, Zone $zone): void
    {
        if (!$this->shown->shows($row, $col, $width, $attributes->height())) {
            // Drawn over: its zone doesn't last
            $this->glyph($row, $col, ' ', $width, $attributes);
            return;
        }
        $this->writeDelimiter($row, $col, $width, $attributes, $zone);
    }

    /**
     * Writes a delimiter starting zone at row, col (not moved), moved: a space, one column
     * wide in column 1 for a character whose right half only stays (moved()). Where the zone
     * starts again (restartAt()), what is drawn there shows once drawn, so it is written
     */
    private function writeDelimiter(int $row, int $col, int $width, Attributes $attributes, Zone $zone): void
    {
        $this->zones->delimiter($row, $col, $width, $attributes->height(), $zone);
        $moved = $this->moved($row, $col, ' ', $attributes);
        if ($moved !== null) {
            [$row, $col, , $attributes] = $moved;
            $this->out->delimiter($row, $col, $attributes->width(), $attributes, $zone);
        }
    }

    /**
     * The character printed before the content, written with REP (Writer::priorCharacter()),
     * moved as the space that stands in for it (StandInSpace, moved()). Where the zone of the
     * columns going off the screen starts again (restartAt()), that space is a delimiter of
     * that zone: its attributes are written just before it, which wait, as in the content, if
     * that character isn't a space
     */
    public function priorCharacter(int $row, int $col, int $width, Attributes $attributes, ?Zone $delimiter): void
    {
        $delimiter ??= $this->restartAt($row, $row, $col, ' ', $attributes);
        (new StandInSpace($row, $col, $width, $attributes, $delimiter))->drawIn($this->zones);
        $moved = $this->moved($row, $col, ' ', $attributes);
        if ($moved === null) {
            return;
        }
        [$row, $col, , $attributes] = $moved;
        $this->out->priorCharacter($row, $col, $attributes->width(), $attributes, $delimiter);
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
     * Where a character drawn at row, col goes, and the bytes and attributes it is written
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
     * it. Another character there is written whole
     *
     * @return ?array{int, int, string, Attributes} the row, the column, the bytes and the
     *                                              attributes
     */
    private function moved(int $row, int $col, string $bytes, Attributes $attributes): ?array
    {
        if ($row !== 0) {
            if ($bytes === ' ' && $this->x < 0 && ($col + $attributes->width() - 1) > ScreenSize::COLS) {
                $attributes = $attributes->narrowed();
            }
            $row += $this->y;
            $col += $this->x;
            if ($row < 1 || $row > ScreenSize::ROWS) {
                return null;
            }
            $attributes = $attributes->writableOn($row);
        }
        if ($col < 1 && ($col + $attributes->width()) > 1) {
            return [$row, 1, ' ', $attributes->narrowed()];
        }
        if ($col < 1 || $col > ScreenSize::COLS) {
            return null;
        }

        return [$row, $col, $bytes, $attributes];
    }

    /**
     * CAN, moved: the cleared cells of the moved row stop at the screen's last column. Moved
     * left, they must stop before the columns that came from beyond the screen: its spaces
     * are written one by one instead, each told to this writer as drawn (ClearedSpaces): the
     * first a delimiter (delimiter(): written while it shows), the others as any space
     * (glyph(): one where the zone of the columns gone off the screen starts again a
     * delimiter, a tall one's top half in the zone of the row above)
     */
    public function clearLine(int $row, int $col, Attributes $attributes, ?Zone $delimiter): void
    {
        if ($row !== 0 && $this->x < 0) {
            (new ClearedSpaces($row, $col, $attributes, $delimiter))->tellTo($this);
            return;
        }
        // A delimiter drawn over, on its row and in double height the row above: its zone
        // doesn't last, nor is it written
        $delimiter = $this->shown->shows($row, $col, width: $attributes->width(), height: $attributes->height())
            ? $delimiter
            : null;
        $zone = $this->zoneBefore($row, $col);
        $this->zones->clearLine($row, $col, $attributes, $delimiter);
        if ($row === 0) {
            $this->out->clearLine(0, $col, $attributes, $zone, $delimiter);
            return;
        }
        $movedRow = $row + $this->y;
        if ($movedRow < 1 || $movedRow > ScreenSize::ROWS) {
            return;
        }
        if (($col + $this->x) <= ScreenSize::COLS) {
            $this->out->clearLine($movedRow, $col + $this->x, $attributes, $zone, $delimiter);
        }
    }

    /**
     * The zone of row restarted (not moved) a delimiter written for bytes drawn at row, col
     * (not moved) starts again, if the zone the columns going off the screen leave starts
     * again there (ShownScreen::restart()): a text cell written as a space (moved(): a
     * space, or a character whose right half only stays in column 1, #170), on a moved row
     * (restarted is row, or the row above for the top half of a tall one)
     */
    private function restartAt(int $restarted, int $row, int $col, string $bytes, Attributes $attributes): ?Zone
    {
        if ($restarted === 0 || $attributes->isMosaic()) {
            return null;
        }
        if (($this->moved($restarted, $col, $bytes, $attributes)[2] ?? null) !== ' ') {
            return null;
        }
        $restart = $this->shown->restart($restarted, 1 - $this->x);
        if ($restart === null || $restart[0] !== $row || $restart[1] !== $col) {
            return null;
        }

        return $restart[2];
    }

    /**
     * The zone a text cell drawn at row, col (not moved) is in, as written: what the cells
     * written before it on its moved row leave, from the column moved to column 1 (moved
     * right, before column 1: the columns from it to column 1, empty, change no zone)
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
