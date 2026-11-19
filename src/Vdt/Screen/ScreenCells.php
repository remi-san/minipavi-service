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
 * As the Minitel shows them, a text cell is underlined when its zone is (Zone): one started,
 * on its row, by the last delimiter before it, with no mosaic or empty cell between them.
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

    /** @var array<int, array<int, Zone>> delimiters, by row, then column: the zone each starts */
    private array $delimiters = [];

    private function __construct()
    {
        $this->cells = array_fill(0, ScreenSize::ROWS + 1, self::emptyRow());
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
            unset($this->delimiters[$row][$col + $i]);
        }
    }

    public function delimiter(int $row, int $col, int $width, Attributes $attributes, Zone $zone): void
    {
        $this->glyph($row, $col, bytes: ' ', width: $width, attributes: $attributes);
        if ($row < 0 || $row > ScreenSize::ROWS) {
            return;
        }
        for ($i = 0; $i < $width && ($col + $i) <= ScreenSize::COLS; $i++) {
            $this->delimiters[$row][$col + $i] = $zone;
        }
    }

    public function sequence(Token $sequence, Cursor $cursor): void
    {
        if ($sequence->type === TokenType::Clear) {
            // The status line stays
            for ($row = 1; $row <= ScreenSize::ROWS; $row++) {
                $this->cells[$row] = self::emptyRow();
                unset($this->delimiters[$row]);
            }
        }
        $row = $cursor->row();
        if ($sequence->type === TokenType::ClearLine && $row >= 0 && $row <= ScreenSize::ROWS) {
            $attributes = $cursor->attributes();
            for ($col = $cursor->col(); $col <= ScreenSize::COLS; $col++) {
                $this->cells[$row][$col] = new Cell(' ', $attributes);
                unset($this->delimiters[$row][$col]);
            }
        }
    }

    /**
     * The cells of a row, each text cell underlined as its zone is: a delimiter starts a
     * zone, and isn't underlined itself; a mosaic or empty cell ends it (MiniPavi's emulator,
     * vdu.js, drawRow())
     *
     * @param array<int, ?Cell> $cells by column
     *
     * @return array<int, ?Cell>
     */
    private function inZones(array $cells, int $row): array
    {
        $zone = Zone::none();
        foreach ($cells as $col => $cell) {
            if ($cell === null || $cell->attributes->isMosaic()) {
                $zone = Zone::none();
                continue;
            }
            $delimiter = $this->delimiters[$row][$col] ?? null;
            $cells[$col] = $cell->inZone($delimiter === null ? $zone : Zone::none());
            $zone = $delimiter ?? $zone;
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
