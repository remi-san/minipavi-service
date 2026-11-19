<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

use MiniPaviService\Vdt\Vdt;

/**
 * Screen drawn by Videotex content: rows 0 (status line) to Minitel\ScreenSize::ROWS, of
 * ScreenSize::COLS cells
 */
final readonly class Screen
{
    /**
     * @param array<int, array<int, ?Cell>> $cells by row, then column (from 1); null if empty
     */
    public function __construct(
        public array $cells,
    ) {}

    public static function of(Vdt $vdt): self
    {
        return new self(ScreenCells::of($vdt));
    }

    public function cell(int $row, int $col): ?Cell
    {
        return $this->cells[$row][$col] ?? null;
    }

    /**
     * Text of a row (0: status line), a character per cell (Cell::text()), empty cells as
     * spaces: ScreenSize::COLS characters, or '' for a row the screen doesn't have
     */
    public function line(int $row): string
    {
        if (!array_key_exists($row, $this->cells)) {
            return '';
        }

        return implode('', array_map(static fn(?Cell $cell) => $cell?->text() ?? ' ', $this->cells[$row]));
    }

    /**
     * Text of the screen: line() of each row, from 0 (status line) to ScreenSize::ROWS, a row
     * per line
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
