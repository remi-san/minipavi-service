<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

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
        $cells = array_fill(0, self::ROWS + 1, array_fill(1, self::COLS, null));
        $vdt->play(
            /** @param array<string, string> $attributes see Attributes */
            static function (int $row, int $col, string $bytes, int $width, array $attributes) use (&$cells): void {
                if ($row >= 0 && $row <= self::ROWS) {
                    for ($i = 0; $i < $width && ($col + $i) <= self::COLS; $i++) {
                        $cells[$row][$col + $i] = new Cell($bytes, $attributes);
                    }
                }
            },
            static function (Token $token, Cursor $cursor) use (&$cells): void {
                if ($token->type === TokenType::Clear) {
                    for ($row = 1; $row <= self::ROWS; $row++) {
                        $cells[$row] = array_fill(1, self::COLS, null);
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
     * Screen as text, a line per row: text characters as they are (G2 ones as ?), mosaic
     * cells as # when they have pixels or a background color, empty cells as spaces
     */
    public function preview(): string
    {
        $lines = [];
        foreach ($this->cells as $row => $cells) {
            $line = '';
            foreach ($cells as $cell) {
                if ($cell === null) {
                    $line .= ' ';
                    continue;
                }
                $mosaic = $cell->attributes['charset'] === Attributes::MOSAIC;
                $background = $cell->attributes['bg'] !== Attributes::BLACK_BACKGROUND;
                $line .= match (true) {
                    $mosaic => $cell->bytes === ' ' && !$background ? ' ' : '#',
                    $cell->bytes[0] === "\x19" || $cell->bytes === "\x1A" => '?',
                    default => $cell->bytes,
                };
            }
            $lines[] = sprintf('%2d|%s|', $row, $line);
        }
        return implode("\n", $lines) . "\n";
    }
}
