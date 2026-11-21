<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Display;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\Terminal;
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
        $attr = Vdt::join(
            $this->size?->toVdt() ?? new Vdt(),
            $this->bg === null ? new Vdt() : new Vdt(Videotex::backgroundColor($this->bg)),
            new Vdt(Videotex::textColor($this->color)),
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
            return Vdt::join(Vdt::position($this->position), $attr, $text);
        }
        if ($this->fill) {
            // Starts at column 1 and pads with spaces up to the text (the spaces having the
            // text's width): the background color only applies from a space onwards. The pad
            // is never negative, a column being at least 1 and a character 1 or 2 columns
            // wide, but Mago types intdiv() as int
            /** @var non-negative-int $pad */
            $pad = intdiv($this->centered($charWidth)->col - 1, $charWidth);

            return Vdt::join(
                Vdt::position(new Position($this->position->row)),
                $attr,
                new Vdt(str_repeat(' ', $pad)),
                $text,
            );
        }

        return Vdt::join(Vdt::position($this->centered($charWidth)), $attr, $text);
    }

    /**
     * First cell of the centered text (Position::centered()), its characters charWidth columns
     * wide. MiniPaviCli::writeCentered counts one column per character, and starts one column
     * too far left when the width is even
     */
    private function centered(int $charWidth): Position
    {
        return Position::centered($this->position->row, mb_strlen($this->text) * $charWidth);
    }
}
