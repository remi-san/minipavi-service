<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Cursor;
use MiniPaviService\Vdt\Screen\Screen;

/**
 * Moves Videotex content by x columns right and y rows down (negative: left, up). Videotex
 * positions are mostly absolute, but text also flows from one cell to the next and wraps at
 * the end of the row, so the content is played (Player) and written again (Writer) rather
 * than having its positions patched. Characters that end up off the screen are dropped
 * (instead of wrapping onto the next row), and the cursor is positioned again where needed,
 * with the current attributes (positioning resets them). Row 0, the status line, is not moved.
 */
final readonly class Offset
{
    public function __construct(
        private int $x,
        private int $y,
    ) {}

    public function apply(Vdt $vdt): Vdt
    {
        $out = new Writer();
        [$x, $y] = [$this->x, $this->y];
        (new Player())->play(
            $vdt,
            static function (int $row, int $col, string $bytes, int $width, Attributes $attributes) use (
                $out,
                $x,
                $y,
            ): void {
                // Row 0 stays where it is; the other rows stay within rows 1 to ROWS
                if ($row !== 0) {
                    $row += $y;
                    $col += $x;
                    if ($row < 1 || $row > Screen::ROWS) {
                        return;
                    }
                }
                if ($col < 1 || ($col + $width - 1) > Screen::COLS) {
                    return;
                }
                $out->glyph($row, $col, $bytes, $width, $attributes);
            },
            static function (Token $token, Cursor $cursor) use ($out, $x, $y): void {
                switch ($token->type) {
                    case TokenType::Clear:
                        $out->clear();
                        break;
                    case TokenType::ClearLine:
                        if ($cursor->row === 0) {
                            $out->clearLine(0, $cursor->col, $cursor->attributes);
                            break;
                        }
                        $row = $cursor->row + $y;
                        if ($row < 1 || $row > Screen::ROWS) {
                            break;
                        }
                        // CAN clears up to the last column: moved left, the cleared cells must
                        // stop before the columns that came from beyond the screen, so they
                        // are written as spaces instead
                        if ($x < 0) {
                            for ($col = max(1, $cursor->col + $x); $col <= (Screen::COLS + $x); $col++) {
                                $out->glyph($row, $col, bytes: ' ', width: 1, attributes: $cursor->attributes);
                            }
                            break;
                        }
                        if (($cursor->col + $x) <= Screen::COLS) {
                            $out->clearLine($row, $cursor->col + $x, $cursor->attributes);
                        }
                        break;
                    case TokenType::Raw:
                        $out->raw($token->bytes);
                        break;
                }
            },
        );
        return new Vdt($out->vdt());
    }
}
