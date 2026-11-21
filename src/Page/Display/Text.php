<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Display;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\ScreenSize;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Vdt\Vdt;
use MiniPaviService\Vdt\Videotex;

/**
 * Text at a position, or centered on its row (<text> in the page XML format)
 *
 * The background color of text is a zone attribute on a Minitel: it starts at a delimiter (a
 * space) and runs along the row up to the next one. So a text with a background color is
 * written after a space of its size, one character before it, or, with no room for it, at
 * the start of the row, the text one character right; when it doesn't fill its row, a space
 * on black after it, when the row has room for it, ends the color, so that it doesn't run over
 * what the row already shows
 */
final readonly class Text implements Element
{
    /**
     * @param bool $fill paints the rest of the row (and, when centered, the start of the row) with bg
     */
    public function __construct(
        private string $text,
        private Position $position,
        private bool $centered,
        private Color $color,
        private ?Color $bg,
        private ?Size $size,
        private bool $fill,
    ) {}

    public function toVdt(Terminal $terminal): Vdt
    {
        // Characters of double width take two columns each
        $charWidth = $this->size?->width() ?? 1;
        [$start, $spaces] = $this->lead($charWidth);

        return Vdt::join(
            Vdt::position(new Position($this->position->row, $start)),
            $this->size?->toVdt() ?? new Vdt(),
            $this->bg === null ? new Vdt() : new Vdt(Videotex::backgroundColor($this->bg)),
            new Vdt(Videotex::textColor($this->color)),
            new Vdt(str_repeat(' ', $spaces)),
            Vdt::text($this->text),
            $this->after($start + (($spaces + mb_strlen($this->text)) * $charWidth), $charWidth),
        );
    }

    /**
     * Where writing starts, and the spaces written there before the text, in its size:
     * centered and filled, from column 1 up to the text (at least one, with a background
     * color); otherwise, with a background color, one, as its delimiter, a character before
     * the text, or in column 1 when there is no room for it, the text one character right;
     * none without
     *
     * @return list{int, non-negative-int} the column, then the number of spaces
     */
    private function lead(int $charWidth): array
    {
        $column = $this->column($charWidth);
        $delimiters = $this->bg === null ? 0 : 1;
        if (!$this->centered || !$this->fill) {
            return [max(1, $column - ($delimiters * $charWidth)), $delimiters];
        }

        // A column being at least 1 and a character 1 or 2 columns wide, it is never
        // negative, but Mago types intdiv() as int
        /** @var non-negative-int $pad */
        $pad = intdiv($column - 1, $charWidth);

        return [1, max($delimiters, $pad)];
    }

    /**
     * What is written after the text, from column next, characters charWidth columns wide:
     * filled, the row cleared on the background color; otherwise, with a background color, a
     * space on black ending it, when the space fits whole on the row. Nothing when the text
     * ends in or past the last column, the cursor being then on the next row
     */
    private function after(int $next, int $charWidth): Vdt
    {
        if ($this->fill) {
            return $next > ScreenSize::COLS ? new Vdt() : new Vdt(Videotex::CLEAR_LINE);
        }
        if ($this->bg === null || ($next + $charWidth - 1) > ScreenSize::COLS) {
            return new Vdt();
        }

        return new Vdt(Videotex::backgroundColor(Color::Black) . ' ');
    }

    /**
     * Column of the text: its position's, or, centered, that of Position::centered(), its
     * characters charWidth columns wide. MiniPaviCli::writeCentered counts one column per
     * character, and starts one column too far left when the width is even
     */
    private function column(int $charWidth): int
    {
        return $this->centered
            ? Position::centered($this->position->row, mb_strlen($this->text) * $charWidth)->col
            : $this->position->col;
    }
}
