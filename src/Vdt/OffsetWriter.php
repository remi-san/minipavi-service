<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

use MiniPaviService\Minitel\ScreenSize;
use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Zone;
use MiniPaviService\Vdt\Screen\Zones;

/**
 * Writes again (Writer) what content draws, moved by x columns right and y rows down: what
 * Offset listens to while playing the content. Characters that end up off the screen are
 * dropped, and row 0, the status line, is not moved. Each text cell is written with the zone
 * it is in (its background, masking and underline), so that the Writer keeps it (as far as it
 * can: a zone whose delimiter goes off the screen starts again at its first space).
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

    private function __construct(
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
        $writer = new self($x, $y);
        (new Player())->play($vdt, $writer);

        return new Vdt($writer->out->vdt());
    }

    public function glyph(int $row, int $col, string $bytes, int $width, Attributes $attributes): void
    {
        $zone = $this->zones->before($row, $col);
        $this->zones->draw($row, $col, $width, $attributes);
        $moved = $this->moved($row, $col, $attributes);
        if ($moved !== null) {
            [$row, $col, $attributes] = $moved;
            $this->out->glyph($row, $col, $bytes, $attributes->width(), $attributes, $zone);
        }
    }

    public function delimiter(int $row, int $col, int $width, Attributes $attributes, Zone $zone): void
    {
        $this->zones->text($row, $col, $width, delimiter: $zone);
        $moved = $this->moved($row, $col, $attributes);
        if ($moved !== null) {
            [$row, $col, $attributes] = $moved;
            $this->out->delimiter($row, $col, $attributes->width(), $attributes, $zone);
        }
    }

    public function sequence(Token $sequence): void
    {
        switch ($sequence->type) {
            case TokenType::Clear:
                $this->zones->clear();
                $this->out->clear();
                break;
            case TokenType::Raw:
                $this->out->raw($sequence->bytes);
                break;
        }
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
        $zone = $this->zones->before($row, $col);
        for ($cleared = $col; $cleared <= ScreenSize::COLS; $cleared++) {
            $this->zones->draw($row, $cleared, width: 1, attributes: $attributes);
        }
        if ($delimiter !== null) {
            $this->zones->text($row, $col, width: 1, delimiter: $delimiter);
        }
        if ($row === 0) {
            $this->out->clearLine(0, $col, $attributes, $zone, $delimiter);
            return;
        }
        $row += $this->y;
        if ($row < 1 || $row > ScreenSize::ROWS) {
            return;
        }
        // CAN clears up to the last column: moved left, the cleared cells must stop before the
        // columns that came from beyond the screen, so they are written as spaces instead
        if ($this->x < 0) {
            for ($cleared = max(1, $col + $this->x); $cleared <= (ScreenSize::COLS + $this->x); $cleared++) {
                if ($delimiter !== null && $cleared === ($col + $this->x)) {
                    $this->out->delimiter($row, $cleared, width: 1, attributes: $attributes, zone: $delimiter);
                    continue;
                }
                $this->out->glyph(
                    $row,
                    $cleared,
                    bytes: ' ',
                    width: 1,
                    attributes: $attributes,
                    zone: $delimiter ?? $zone,
                );
            }
            return;
        }
        if (($col + $this->x) <= ScreenSize::COLS) {
            $this->out->clearLine($row, $col + $this->x, $attributes, $zone, $delimiter);
        }
    }
}
