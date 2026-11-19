<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

use MiniPaviService\Minitel\ScreenSize;
use MiniPaviService\Vdt\Player;
use MiniPaviService\Vdt\ScreenListener;
use MiniPaviService\Vdt\Token;
use MiniPaviService\Vdt\TokenType;
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

    public function glyph(int $row, int $col, string $bytes, int $width, Attributes $attributes): void
    {
        if ($row < 0 || $row > ScreenSize::ROWS) {
            return;
        }
        for ($i = 0; $i < $width && ($col + $i) <= ScreenSize::COLS; $i++) {
            $this->cells[$row][$col + $i] = new Cell($bytes, $attributes);
            $this->zones->draw($row, $col + $i, width: 1, attributes: $attributes);
        }
    }

    public function delimiter(int $row, int $col, int $width, Attributes $attributes, Zone $zone): void
    {
        $this->glyph($row, $col, bytes: ' ', width: $width, attributes: $attributes);
        if ($row >= 0 && $row <= ScreenSize::ROWS) {
            $this->zones->text($row, $col, $width, delimiter: $zone);
        }
    }

    public function clearLine(int $row, int $col, Attributes $attributes, ?Zone $delimiter): void
    {
        for ($cleared = $col; $cleared <= ScreenSize::COLS; $cleared++) {
            $this->glyph($row, $cleared, bytes: ' ', width: 1, attributes: $attributes);
        }
        if ($delimiter !== null) {
            $this->delimiter($row, $col, width: 1, attributes: $attributes, zone: $delimiter);
        }
    }

    public function sequence(Token $sequence): void
    {
        if ($sequence->type === TokenType::Clear) {
            // The status line stays
            for ($row = 1; $row <= ScreenSize::ROWS; $row++) {
                $this->cells[$row] = self::emptyRow();
            }
            $this->zones->clear();
        }
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
