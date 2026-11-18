<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Display;

use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Vdt\Screen\Screen;
use MiniPaviService\Vdt\Vdt;
use MiniPaviService\Vdt\Videotex;

/**
 * Text at a position, or centered on its row (<text> in the page XML format)
 */
final readonly class Text implements Element
{
    /**
     * @param bool $fill paints the rest of the row (and, when centered, the start of the row) with bg
     */
    public function __construct(
        public string $text,
        public Position $position,
        public bool $centered = false,
        public Color $color = Color::White,
        public ?Color $bg = null,
        public ?Size $size = null,
        public bool $fill = false,
    ) {}

    public function toVdt(Terminal $terminal): Vdt
    {
        $attr = Vdt::join(
            $this->size?->toVdt() ?? new Vdt(),
            $this->bg?->background() ?? new Vdt(),
            $this->color->text(),
        );
        // Characters of double width take two columns each
        $charWidth = $this->size?->width() ?? 1;
        $vdt = $this->placed($attr, Vdt::text($this->text), $charWidth);

        // Fills the rest of the row with the background color
        return $this->fill ? $vdt->append(new Vdt(Videotex::CLEAR_LINE)) : $vdt;
    }

    /**
     * The text, after its attributes, at its position, or centered on its row
     */
    private function placed(Vdt $attr, Vdt $text, int $charWidth): Vdt
    {
        if (!$this->centered) {
            return Vdt::join(Vdt::position($this->position->col, $this->position->row), $attr, $text);
        }
        if ($this->fill) {
            // Starts at column 1 and pads with spaces up to the text (the spaces having the
            // text's width): the background color only applies from a space onwards
            $pad = max(0, intdiv($this->centeredCol($charWidth) - 1, $charWidth));

            return Vdt::join(Vdt::position(1, $this->position->row), $attr, new Vdt(str_repeat(' ', $pad)), $text);
        }

        return Vdt::join(Vdt::position($this->centeredCol($charWidth), $this->position->row), $attr, $text);
    }

    /**
     * First column of the centered text, with as many columns on its left as on its right (one
     * more on the right when they can't be equal). MiniPaviCli::writeCentered counts one column
     * per character, and starts one column too far left when the width is even
     */
    private function centeredCol(int $charWidth): int
    {
        $width = mb_strlen($this->text) * $charWidth;

        return $width >= Screen::COLS ? 1 : intdiv(Screen::COLS - $width, num2: 2) + 1;
    }
}
