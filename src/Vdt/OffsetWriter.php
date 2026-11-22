<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

use MiniPaviService\Minitel\ScreenSize;
use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\ScreenZones;
use MiniPaviService\Vdt\Screen\Zone;
use MiniPaviService\Vdt\Screen\Zones;

/**
 * Writes again (Writer) what content draws, moved by x columns right and y rows down: what
 * Offset listens to while playing the content. Characters that end up off the screen are
 * dropped, and row 0, the status line, is not moved. Each text cell is written with the zone
 * it is in (its background, masking and underline), so that the Writer keeps it (as far as it
 * can: a zone whose delimiter goes off the screen starts again at its first space). On a row
 * moved left, what the columns going off the screen leave is the zone they show once the
 * content is drawn (zoneBefore()): what is drawn there isn't written, but may start or end,
 * after the cells on the screen, the zone they show.
 *
 * Its Writer keeps where it is and what it wrote (so it isn't readonly), so it listens to one
 * content only: it is only built, and played to, by write().
 *
 * @internal
 */
final class OffsetWriter implements ScreenListener
{
    private readonly Writer $out;
    // The zones of the content, where it draws them (not moved)
    private readonly Zones $zones;

    /**
     * @param Zones $shown the zones of the content once it is drawn (not moved)
     */
    private function __construct(
        private readonly Zones $shown,
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
        $writer = new self(ScreenZones::of($vdt), $x, $y);
        (new Player())->play($vdt, $writer);

        return new Vdt($writer->out->vdt());
    }

    public function glyph(int $row, int $col, string $bytes, int $width, Attributes $attributes): void
    {
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
        $this->zones->delimiter($row, $col, $width, $attributes->height(), $zone);
        $moved = $this->moved($row, $col, $attributes);
        if ($moved !== null) {
            [$row, $col, $attributes] = $moved;
            $this->out->delimiter($row, $col, $attributes->width(), $attributes, $zone);
        }
    }

    public function clear(): void
    {
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
        // columns that came from beyond the screen, so they are written as spaces instead, each
        // in the zone it is in (a delimiter gone off the screen may not show any more)
        if ($this->x < 0) {
            for ($cleared = max(1, $col + $this->x); $cleared <= (ScreenSize::COLS + $this->x); $cleared++) {
                if ($delimiter !== null && $cleared === ($col + $this->x)) {
                    $this->out->delimiter($movedRow, $cleared, width: 1, attributes: $attributes, zone: $delimiter);
                    continue;
                }
                $this->out->glyph(
                    $movedRow,
                    $cleared,
                    bytes: ' ',
                    width: 1,
                    attributes: $attributes,
                    zone: $this->zoneBefore($row, $cleared - $this->x),
                );
            }
            return;
        }
        if (($col + $this->x) <= ScreenSize::COLS) {
            $this->out->clearLine($movedRow, $col + $this->x, $attributes, $zone, $delimiter);
        }
    }

    /**
     * The zone a text cell drawn at row, col (not moved) is in, as the moved row shows it: on
     * a row moved left, the zone the columns going off the screen leave once the content is
     * drawn (what is drawn there later isn't written, but changes it), then what the cells
     * drawn so far after them leave
     */
    private function zoneBefore(int $row, int $col): Zone
    {
        if ($row === 0) {
            // Not moved
            return $this->zones->before($row, $col);
        }
        // The column moved to column 1: moved right, it is before column 1, and the columns
        // from it to column 1, empty, change no zone
        $first = 1 - $this->x;

        return $this->zones->beforeFrom($this->shown->before($row, $first), $row, $first, $col);
    }
}
