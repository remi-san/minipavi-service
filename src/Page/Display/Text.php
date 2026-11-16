<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Display;

use MiniPaviService\MiniPaviClient;
use MiniPaviService\Vdt\Screen;
use MiniPaviService\Vdt\VdtHelper;
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
        public int $row,
        public int $col = 1,
        public bool $centered = false,
        public Color $color = Color::White,
        public ?Color $bg = null,
        public ?Size $size = null,
        public bool $fill = false,
    ) {
        Position::check($row, $col);
    }

    public function getContent(MiniPaviClient $client): string
    {
        $attr = ($this->size?->vdt() ?? '') . ($this->bg?->background() ?? '') . $this->color->text();
        // Characters of double width take two columns each
        $charWidth = $this->size?->width() ?? 1;
        if ($this->centered) {
            // First column of the text, with as many columns on its left as on its right (one
            // more on the right when they can't be equal). MiniPaviCli::writeCentered counts one
            // column per character, and starts one column too far left when the width is even
            $width = mb_strlen($this->text) * $charWidth;
            $col = $width >= Screen::COLS ? 1 : intdiv(Screen::COLS - $width, 2) + 1;
        }
        if ($this->centered && $this->fill) {
            // Starts at column 1 and pads with spaces up to the text (the spaces having the
            // text's width): the background color only applies from a space onwards
            $pad = intdiv($col - 1, $charWidth);
            $vdt = VdtHelper::setPos(1, $this->row) . $attr . str_repeat(' ', $pad) . VdtHelper::toG2($this->text);
        } elseif ($this->centered) {
            $vdt = VdtHelper::setPos($col, $this->row) . $attr . VdtHelper::toG2($this->text);
        } else {
            $vdt = VdtHelper::setPos($this->col, $this->row) . $attr . VdtHelper::toG2($this->text);
        }
        // Fills the rest of the row with the background color
        if ($this->fill) {
            $vdt .= Videotex::CLEAR_LINE;
        }
        return $vdt;
    }
}
