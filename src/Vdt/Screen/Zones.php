<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

use MiniPaviService\Minitel\ScreenSize;

/**
 * The zones of a screen, by where its cells are, as the Minitel draws them (MiniPavi's
 * emulator, vdu.js, drawRow()): a text cell is in the zone the last delimiter before it on its
 * row started, if no mosaic or empty cell is between them. It knows which cells are text, and
 * which are delimiters, not what they show.
 *
 * It changes as cells are drawn (so it isn't readonly).
 *
 * @internal
 */
final class Zones
{
    /**
     * @var array<int, array<int, ?Zone>> the text cells, by row, then column (from 1): the zone
     *                                    a delimiter starts, null for another text cell; a mosaic
     *                                    or empty cell has no entry
     */
    private array $text = [];

    /**
     * Draws a text character at row, col, width cells wide: a delimiter starting zone, or
     * another one if delimiter is null
     */
    public function text(int $row, int $col, int $width, ?Zone $delimiter = null): void
    {
        for ($i = 0; $i < $width && ($col + $i) <= ScreenSize::COLS; $i++) {
            $this->text[$row][$col + $i] = $delimiter;
        }
    }

    /**
     * Draws a mosaic character at row, col, which ends a zone
     */
    public function mosaic(int $row, int $col): void
    {
        unset($this->text[$row][$col]);
    }

    /**
     * Clears rows 1 to ROWS (a clear screen: the status row stays)
     */
    public function clear(): void
    {
        for ($row = 1; $row <= ScreenSize::ROWS; $row++) {
            unset($this->text[$row]);
        }
    }

    /**
     * Whether the cell at row, col is a delimiter
     */
    public function isDelimiter(int $row, int $col): bool
    {
        return ($this->text[$row][$col] ?? null) !== null;
    }

    /**
     * The zone a text cell at row, col is in, if it isn't a delimiter: the one the last
     * delimiter before it started, or none if a mosaic or empty cell comes first
     */
    public function before(int $row, int $col): Zone
    {
        for ($before = $col - 1; $before >= 1; $before--) {
            if (!\array_key_exists($before, $this->text[$row] ?? [])) {
                return Zone::none();
            }
            $delimiter = $this->text[$row][$before];
            if ($delimiter !== null) {
                return $delimiter;
            }
        }

        return Zone::none();
    }

    /**
     * The zone a text cell at row, col shows: a delimiter isn't in one (Zone::none()), another
     * cell is in the one before it (before())
     */
    public function of(int $row, int $col): Zone
    {
        return $this->isDelimiter($row, $col) ? Zone::none() : $this->before($row, $col);
    }
}
