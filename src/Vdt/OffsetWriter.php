<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

use MiniPaviService\Minitel\ScreenSize;
use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Cursor;

/**
 * Writes again (Writer) what content draws, moved by x columns right and y rows down: what
 * Offset listens to while playing the content. Characters that end up off the screen are
 * dropped, and row 0, the status line, is not moved.
 *
 * Its Writer keeps where it is and what it wrote, so it listens to one content only: it is
 * only built, and played to, by write().
 *
 * @internal
 */
final readonly class OffsetWriter implements ScreenListener
{
    private Writer $out;

    private function __construct(
        private int $x,
        private int $y,
    ) {
        $this->out = new Writer();
    }

    /**
     * The content, moved
     */
    public static function write(Vdt $vdt, int $x, int $y): Vdt
    {
        $writer = new self($x, $y);
        (new Player())->play($vdt, $writer);

        return new Vdt($writer->out->vdt());
    }

    public function glyph(int $row, int $col, string $bytes, int $width, Attributes $attributes): void
    {
        // Row 0 stays where it is; the other rows stay within rows 1 to ROWS
        if ($row !== 0) {
            $row += $this->y;
            $col += $this->x;
            if ($row < 1 || $row > ScreenSize::ROWS) {
                return;
            }
            // Written with a size the Minitel accepts on the row it is moved to (no ESC M or ESC O
            // on row 1)
            $attributes = $attributes->writableOn($row);
            $width = $attributes->width();
        }
        if ($col < 1 || ($col + $width - 1) > ScreenSize::COLS) {
            return;
        }
        $this->out->glyph($row, $col, $bytes, $width, $attributes);
    }

    public function sequence(Token $sequence, Cursor $cursor): void
    {
        switch ($sequence->type) {
            case TokenType::Clear:
                $this->out->clear();
                break;
            case TokenType::ClearLine:
                $this->clearLine($cursor);
                break;
            case TokenType::Raw:
                $this->out->raw($sequence->bytes);
                break;
        }
    }

    private function clearLine(Cursor $cursor): void
    {
        $col = $cursor->col();
        $attributes = $cursor->attributes();
        if ($cursor->row() === 0) {
            $this->out->clearLine(0, $col, $attributes);
            return;
        }
        $row = $cursor->row() + $this->y;
        if ($row < 1 || $row > ScreenSize::ROWS) {
            return;
        }
        // CAN clears up to the last column: moved left, the cleared cells must stop before the
        // columns that came from beyond the screen, so they are written as spaces instead
        if ($this->x < 0) {
            for ($cleared = max(1, $col + $this->x); $cleared <= (ScreenSize::COLS + $this->x); $cleared++) {
                $this->out->glyph($row, $cleared, bytes: ' ', width: 1, attributes: $attributes);
            }
            return;
        }
        if (($col + $this->x) <= ScreenSize::COLS) {
            $this->out->clearLine($row, $col + $this->x, $attributes);
        }
    }
}
