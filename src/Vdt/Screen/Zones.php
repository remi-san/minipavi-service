<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Minitel\ScreenSize;

/**
 * The zones of a screen, by where its cells are, as the Minitel draws them (MiniPavi's
 * emulator, vdu.js, drawRow()): along each row from column 1, a delimiter starts its zone; a
 * mosaic or empty cell ends its background and underline, the cells after it on its
 * background, but not its masking, which runs up to the next delimiter. It knows which cells
 * are text, which are delimiters, and the background of the mosaic ones, not what they show.
 *
 * It changes as cells are drawn (so it isn't readonly).
 *
 * @internal
 */
final class Zones
{
    /**
     * @var array<int, array<int, ?Zone>> the text cells, by row, then column (from 1): the zone
     *                                    a delimiter starts, null for another text cell
     */
    private array $text = [];

    /**
     * @var array<int, array<int, Color>> the background of the mosaic cells, by row, then
     *                                    column (from 1); one a text cell was drawn over may
     *                                    stay, as the text cells are read first
     */
    private array $mosaic = [];

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
     * Draws a character at row, col, width cells wide, with these attributes: a text one
     * (text()), or a mosaic one, on its background
     */
    public function draw(int $row, int $col, int $width, Attributes $attributes): void
    {
        if ($attributes->isMosaic()) {
            unset($this->text[$row][$col]);
            $this->mosaic[$row][$col] = $attributes->background();
            return;
        }
        $this->text($row, $col, $width);
    }

    /**
     * Clears rows 1 to ROWS (a clear screen: the status row stays)
     */
    public function clear(): void
    {
        for ($row = 1; $row <= ScreenSize::ROWS; $row++) {
            unset($this->text[$row], $this->mosaic[$row]);
        }
    }

    /**
     * The zone a text cell at row, col is in, if it isn't a delimiter: what the cells before it
     * on its row leave, from column 1 (drawRow())
     */
    public function before(int $row, int $col): Zone
    {
        $zone = Zone::none();
        for ($before = 1; $before < $col; $before++) {
            $zone = $this->after($zone, $row, $before);
        }

        return $zone;
    }

    /**
     * What the cell at row, col shows (Zone::shownWith()): a text cell the zone it is in
     * (before()), a delimiter, mosaic or empty cell its own background, with the masking
     * before it
     */
    public function of(int $row, int $col): Zone
    {
        $before = $this->before($row, $col);
        if (!\array_key_exists($col, $this->text[$row] ?? [])) {
            return $before->on($this->mosaic[$row][$col] ?? Color::Black);
        }
        $delimiter = $this->text[$row][$col];

        return $delimiter === null ? $before : $before->on($delimiter->background());
    }

    /**
     * The zone after the cell at row, col, from zone before it: the one a delimiter starts,
     * the same after another text cell, its background after a mosaic or empty one
     */
    private function after(Zone $zone, int $row, int $col): Zone
    {
        if (!\array_key_exists($col, $this->text[$row] ?? [])) {
            return $zone->on($this->mosaic[$row][$col] ?? Color::Black);
        }

        return $this->text[$row][$col] ?? $zone;
    }
}
