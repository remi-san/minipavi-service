<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

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
        $this->cells = array_fill(0, Screen::ROWS + 1, self::emptyRow());
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
        if ($row < 0 || $row > Screen::ROWS) {
            return;
        }
        for ($i = 0; $i < $width && ($col + $i) <= Screen::COLS; $i++) {
            $this->cells[$row][$col + $i] = new Cell($bytes, $attributes);
        }
    }

    public function sequence(Token $sequence, Cursor $cursor): void
    {
        if ($sequence->type === TokenType::Clear) {
            // The status line stays
            for ($row = 1; $row <= Screen::ROWS; $row++) {
                $this->cells[$row] = self::emptyRow();
            }
        }
        if ($sequence->type === TokenType::ClearLine && $cursor->row >= 0 && $cursor->row <= Screen::ROWS) {
            for ($col = $cursor->col; $col <= Screen::COLS; $col++) {
                $this->cells[$cursor->row][$col] = new Cell(' ', $cursor->attributes);
            }
        }
    }

    /**
     * @return array<int, null>
     */
    private static function emptyRow(): array
    {
        return array_fill(1, Screen::COLS, value: null);
    }
}
