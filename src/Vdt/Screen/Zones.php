<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\ScreenSize;

/**
 * The zones of a screen, by where its cells are, as the Minitel draws them (MiniPavi's
 * emulator, vdu.js, drawRow()): along each row from column 1, a delimiter starts its zone; a
 * mosaic or empty cell ends its background and underline, the cells after it on its
 * background, but not its masking, which runs up to the next delimiter. A tall character (a
 * delimiter or text, in double height or double size) is drawn on the row above too, where
 * its top half is, as the emulator draws it (printDelimiter(), printG0Char()): a tall
 * delimiter starts its zone on both rows, and tall text is text on both. It knows which
 * cells are text, which are delimiters, and the background of the mosaic ones, not what
 * they show.
 *
 * A character is drawn at a position, with attributes giving how many columns and rows it
 * takes; the cells it covers, and those read along a row, are rows and columns, as they may
 * be off the screen (cells(): the right half of a double-width character in the last column).
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
     * Draws a delimiter starting zone at position, as wide and as high as these attributes
     * make it (cells()): on the row above too, when it is tall
     */
    public function delimiter(Position $position, Attributes $attributes, Zone $zone): void
    {
        $cells = self::cells($position->row, $position->col, $attributes->width(), $attributes->height());
        foreach ($cells as [$cellRow, $cellCol]) {
            $this->text[$cellRow][$cellCol] = $zone;
        }
    }

    /**
     * Draws a character at position, with these attributes: a text one, or a mosaic one, on
     * its background; a tall one (Attributes::height()) on the row above too (cells())
     */
    public function draw(Position $position, Attributes $attributes): void
    {
        $this->drawAt($position->row, $position->col, $attributes);
    }

    /**
     * CAN at position: spaces with these attributes up to the last column (draw()), the first
     * one a delimiter starting zone delimiter, if not null (delimiter()): in double height, on
     * the row above too, as the emulator prints them (decoder.js, clear("eol"); a CAN in
     * double width is told as its spaces, Pen::clearLine())
     */
    public function clearLine(Position $position, Attributes $attributes, ?Zone $delimiter): void
    {
        for ($cleared = $position->col; $cleared <= ScreenSize::COLS; $cleared++) {
            $this->drawAt($position->row, $cleared, $attributes);
        }
        if ($delimiter !== null) {
            $this->delimiter($position, $attributes, $delimiter);
        }
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
        return $this->beforeFrom(Zone::none(), $row, from: 1, col: $col);
    }

    /**
     * The zone a text cell at row, col is in, if it isn't a delimiter, when the cells before
     * column from leave zone: what the cells from that column to it leave (before())
     */
    public function beforeFrom(Zone $zone, int $row, int $from, int $col): Zone
    {
        for ($before = $from; $before < $col; $before++) {
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
     * The cells of a character drawn at row, col, width cells wide and height cells high:
     * its bottom row is the cursor's, the row above its top half's (row 0, the status row,
     * above row 1). A cell past the last column (a double-width character in it) is kept,
     * but never read
     *
     * @return list<array{int, int}> each cell's row and column
     */
    public static function cells(int $row, int $col, int $width, int $height): array
    {
        $cells = [];
        for ($above = 0; $above < $height; $above++) {
            for ($i = 0; $i < $width; $i++) {
                $cells[] = [$row - $above, $col + $i];
            }
        }

        return $cells;
    }

    /**
     * Draws a character at row, col, with these attributes, in each cell it covers (cells())
     */
    private function drawAt(int $row, int $col, Attributes $attributes): void
    {
        foreach (self::cells($row, $col, $attributes->width(), $attributes->height()) as [$cellRow, $cellCol]) {
            $this->drawCell($cellRow, $cellCol, $attributes);
        }
    }

    /**
     * Draws a character in the cell at row, col, with these attributes: a text one, or a
     * mosaic one, on its background
     */
    private function drawCell(int $row, int $col, Attributes $attributes): void
    {
        if ($attributes->isMosaic()) {
            unset($this->text[$row][$col]);
            $this->mosaic[$row][$col] = $attributes->background();
            return;
        }
        $this->text[$row][$col] = null;
    }

    /**
     * The zone after the cell at row, col, from zone before it: the one a delimiter starts,
     * the same after another text cell, its background after a mosaic or empty one
     */
    public function after(Zone $zone, int $row, int $col): Zone
    {
        if (!\array_key_exists($col, $this->text[$row] ?? [])) {
            return $zone->on($this->mosaic[$row][$col] ?? Color::Black);
        }

        return $this->text[$row][$col] ?? $zone;
    }
}
