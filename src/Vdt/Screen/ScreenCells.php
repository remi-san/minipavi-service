<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

use MiniPaviService\Minitel\ScreenSize;
use MiniPaviService\Vdt\Player;
use MiniPaviService\Vdt\ScreenListener;
use MiniPaviService\Vdt\Vdt;

/**
 * The cells of the screen as content draws them: what Screen::of() collects while playing it.
 * As the Minitel shows them, each cell has the background, masking and (in text) underline of
 * its zone (Zone, Zones::of()).
 *
 * It fills its cells as it listens (so it isn't readonly), for one content only: it is only
 * built, and played to, by of().
 *
 * @internal
 */
final class ScreenCells implements ScreenListener
{
    /** @var array<int, array<int, ?Cell>> by row, then column (from 1); null if empty */
    private array $cells;

    private Zones $zones;

    private function __construct()
    {
        $this->cells = array_fill(0, ScreenSize::ROWS + 1, self::emptyRow());
        $this->zones = new Zones();
    }

    /**
     * The cells the content draws, by row, then column (from 1); null if empty
     *
     * @return array<int, array<int, ?Cell>>
     */
    public static function of(Vdt $vdt): array
    {
        $cells = new self();
        (new Player())->play($vdt, $cells);

        return array_map($cells->inZones(...), $cells->cells, array_keys($cells->cells));
    }

    /**
     * A character drawn at row, col: in each cell it covers (Zones::cells()), the part of it
     * the cell shows (Part), as MiniPavi's emulator draws it (decoder.js, printG0Char()): a
     * tall one's top half on the row above, over what is there; a wide one in the last
     * column its left half only
     */
    public function glyph(int $row, int $col, string $bytes, int $width, Attributes $attributes): void
    {
        foreach (Zones::cells($row, $col, $width, $attributes->height()) as [$cellRow, $cellCol]) {
            if ($cellCol > ScreenSize::COLS) {
                continue;
            }
            $part = new Part(right: $cellCol > $col, bottom: $cellRow === $row && $attributes->height() > 1);
            $this->cells[$cellRow][$cellCol] = new Cell($bytes, $attributes, $part);
        }
        $this->zones->draw($row, $col, $width, $attributes);
    }

    public function delimiter(int $row, int $col, int $width, Attributes $attributes, Zone $zone): void
    {
        $this->glyph($row, $col, bytes: ' ', width: $width, attributes: $attributes);
        $this->zones->delimiter($row, $col, $width, $attributes->height(), $zone);
    }

    /**
     * CAN at row, col, as MiniPavi's emulator prints it (decoder.js, clear("eol")): a space
     * with these attributes for each column from col to the last, the first one a delimiter
     * starting zone delimiter, if not null, each where the cursor goes after the one before
     * (Cursor::advance()). So in double height they clear the row above too, and in double
     * width they run on over the next row (two rows down in double size), as text wraps
     */
    public function clearLine(int $row, int $col, Attributes $attributes, ?Zone $delimiter): void
    {
        $cursor = Cursor::at($row, $col, $attributes);
        $width = $attributes->width();
        for ($space = $col; $space <= ScreenSize::COLS; $space++) {
            $this->glyph($cursor->row(), $cursor->col(), bytes: ' ', width: $width, attributes: $attributes);
            if ($delimiter !== null && $space === $col) {
                $this->zones->delimiter($row, $col, $width, $attributes->height(), $delimiter);
            }
            $cursor->advance($width);
        }
    }

    public function clear(): void
    {
        // The status line stays
        for ($row = 1; $row <= ScreenSize::ROWS; $row++) {
            $this->cells[$row] = self::emptyRow();
        }
        $this->zones->clear();
    }

    public function raw(string $bytes): void
    {
        // Changes nothing the screen shows
    }

    /**
     * The cells of a row, each as its zone shows it (Zones::of())
     *
     * @param array<int, ?Cell> $cells by column
     *
     * @return array<int, ?Cell>
     */
    private function inZones(array $cells, int $row): array
    {
        foreach ($cells as $col => $cell) {
            if ($cell === null) {
                continue;
            }
            $cells[$col] = $cell->inZone($this->zones->of($row, $col));
        }

        return $cells;
    }

    /**
     * @return array<int, null>
     */
    private static function emptyRow(): array
    {
        return array_fill(1, ScreenSize::COLS, value: null);
    }
}
