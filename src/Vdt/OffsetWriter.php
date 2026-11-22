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
 * delimiter written in the first text space it shows in (ShownScreen::restart()), as what is
 * drawn there isn't written: what is before that space has no column left for one, and loses
 * it. A delimiter something is drawn over later is written as a space, as its zone doesn't
 * last. Each text cell is written with the zone the cells written before it leave (zoneBefore()),
 * so that the Writer starts no other.
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
        $restart = $this->restartAt($row, $col, $bytes, $attributes);
        if ($restart !== null) {
            $this->delimiter($row, $col, $width, $attributes, $restart);
            return;
        }
        $zone = $this->zoneBefore($row, $col);
        $this->zones->draw($row, $col, $width, $attributes);
        $moved = $this->moved($row, $col, $attributes);
        if ($moved !== null) {
            [$row, $col, $attributes] = $moved;
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
        $this->zones->delimiter($row, $col, $width, $attributes->height(), $zone);
        $moved = $this->moved($row, $col, $attributes);
        if ($moved !== null) {
            [$row, $col, $attributes] = $moved;
            $this->out->delimiter($row, $col, $attributes->width(), $attributes, $zone);
        }
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
     * Where a character drawn at row, col goes, and the attributes it is written with; null if
     * off the screen. Row 0 stays where it is; the other rows stay within rows 1 to ROWS, the
     * character written with a size the Minitel accepts on the row it is moved to (no ESC M or
     * ESC O on row 1)
     *
     * @return ?array{int, int, Attributes} the row, the column and the attributes
     */
    private function moved(int $row, int $col, Attributes $attributes): ?array
    {
        if ($row !== 0) {
            $row += $this->y;
            $col += $this->x;
            if ($row < 1 || $row > ScreenSize::ROWS) {
                return null;
            }
            $attributes = $attributes->writableOn($row);
        }
        if ($col < 1 || ($col + $attributes->width() - 1) > ScreenSize::COLS) {
            return null;
        }

        return [$row, $col, $attributes];
    }

    /**
     * CAN, moved: the cleared cells of the moved row stop at the screen's last column
     */
    public function clearLine(int $row, int $col, Attributes $attributes, ?Zone $delimiter): void
    {
        // A delimiter drawn over: its zone doesn't last, nor is it written
        $delimiter = $this->shown->shows($row, $col, width: 1, height: 1) ? $delimiter : null;
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
        // CAN clears up to the last column: moved left, the cleared cells must stop before the
        // columns that came from beyond the screen, so they are written as spaces instead (one
        // where the zone of the columns gone off the screen starts again a delimiter)
        if ($this->x < 0) {
            for ($cleared = max(1, $col + $this->x); $cleared <= (ScreenSize::COLS + $this->x); $cleared++) {
                $from = $cleared - $this->x;
                if ($delimiter !== null && $from === $col) {
                    $this->out->delimiter($movedRow, $cleared, width: 1, attributes: $attributes, zone: $delimiter);
                    continue;
                }
                $restart = $this->restartAt($row, $from, bytes: ' ', attributes: $attributes);
                if ($restart !== null) {
                    $this->delimiter($row, $from, width: 1, attributes: $attributes, zone: $restart);
                    continue;
                }
                $this->out->glyph(
                    $movedRow,
                    $cleared,
                    bytes: ' ',
                    width: 1,
                    attributes: $attributes,
                    zone: $this->zoneBefore($row, $from),
                );
            }
            return;
        }
        if (($col + $this->x) <= ScreenSize::COLS) {
            $this->out->clearLine($movedRow, $col + $this->x, $attributes, $zone, $delimiter);
        }
    }

    /**
     * The zone a delimiter written for bytes drawn at row, col (not moved) starts again, if
     * the zone the columns going off the screen leave starts again there (ShownScreen::restart()):
     * a text space, on a moved row
     */
    private function restartAt(int $row, int $col, string $bytes, Attributes $attributes): ?Zone
    {
        if ($row === 0 || $bytes !== ' ' || $attributes->isMosaic()) {
            return null;
        }
        $restart = $this->shown->restart($row, 1 - $this->x);
        if ($restart === null || $restart[0] !== $col) {
            return null;
        }

        return $restart[1];
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
