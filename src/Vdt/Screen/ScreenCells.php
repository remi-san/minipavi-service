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

        return $cells->cells;
    }

    public function glyph(int $row, int $col, string $bytes, int $width, Attributes $attributes): void
    {
        if ($row < 0 || $row > ScreenSize::ROWS) {
            return;
        }
        for ($i = 0; $i < $width && ($col + $i) <= ScreenSize::COLS; $i++) {
            $this->cells[$row][$col + $i] = new Cell($bytes, $attributes);
        }
    }

    public function sequence(Token $sequence, Cursor $cursor): void
    {
        if ($sequence->type === TokenType::Clear) {
            // The status line stays
            for ($row = 1; $row <= ScreenSize::ROWS; $row++) {
                $this->cells[$row] = self::emptyRow();
            }
        }
        $row = $cursor->row();
        if ($sequence->type === TokenType::ClearLine && $row >= 0 && $row <= ScreenSize::ROWS) {
            $attributes = $cursor->attributes();
            for ($col = $cursor->col(); $col <= ScreenSize::COLS; $col++) {
                $this->cells[$row][$col] = new Cell(' ', $attributes);
            }
        }
    }

    /**
     * @return array<int, null>
     */
    private static function emptyRow(): array
    {
        return array_fill(1, ScreenSize::COLS, value: null);
    }
}
