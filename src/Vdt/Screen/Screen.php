<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

use MiniPaviService\Vdt\Player;
use MiniPaviService\Vdt\Token;
use MiniPaviService\Vdt\TokenType;
use MiniPaviService\Vdt\Vdt;

/**
 * Screen drawn by Videotex content: rows 0 (status line) to ROWS, of COLS cells. ROWS and
 * COLS are the size of the Minitel screen for the whole framework.
 */
final readonly class Screen
{
    public const ROWS = 24;
    public const COLS = 40;

    /**
     * @param array<int, array<int, ?Cell>> $cells by row, then column (from 1); null if empty
     */
    public function __construct(
        public array $cells,
    ) {}

    public static function of(Vdt $vdt): self
    {
        $cells = array_fill(0, self::ROWS + 1, array_fill(1, self::COLS, value: null));
        (new Player())->play(
            $vdt,
            static function (int $row, int $col, string $bytes, int $width, Attributes $attributes) use (
                &$cells,
            ): void {
                if ($row >= 0 && $row <= self::ROWS) {
                    for ($i = 0; $i < $width && ($col + $i) <= self::COLS; $i++) {
                        $cells[$row][$col + $i] = new Cell($bytes, $attributes);
                    }
                }
            },
            static function (Token $token, Cursor $cursor) use (&$cells): void {
                if ($token->type === TokenType::Clear) {
                    for ($row = 1; $row <= self::ROWS; $row++) {
                        $cells[$row] = array_fill(1, self::COLS, value: null);
                    }
                }
                if ($token->type === TokenType::ClearLine && $cursor->row >= 0 && $cursor->row <= self::ROWS) {
                    for ($col = $cursor->col; $col <= self::COLS; $col++) {
                        $cells[$cursor->row][$col] = new Cell(' ', $cursor->attributes);
                    }
                }
            },
        );
        return new self($cells);
    }

    public function cell(int $row, int $col): ?Cell
    {
        return $this->cells[$row][$col] ?? null;
    }

    /**
     * Text of a row (0: status line), a character per cell (Cell::text()), empty cells as
     * spaces: COLS characters, or '' for a row the screen doesn't have
     */
    public function line(int $row): string
    {
        if (!array_key_exists($row, $this->cells)) {
            return '';
        }

        return implode('', array_map(static fn(?Cell $cell) => $cell?->text() ?? ' ', $this->cells[$row]));
    }

    /**
     * Text of the screen: line() of each row, from 0 (status line) to ROWS, a row per line
     */
    public function text(): string
    {
        return implode("\n", array_map($this->line(...), array_keys($this->cells)));
    }

    /**
     * Screen as text, to read in a terminal: line() of each row, framed and numbered
     * (" 0|...|" for the status line), a row per line, ending with a line break
     */
    public function preview(): string
    {
        $lines = '';
        foreach (array_keys($this->cells) as $row) {
            $lines .= \sprintf("%2d|%s|\n", $row, $this->line($row));
        }

        return $lines;
    }
}
