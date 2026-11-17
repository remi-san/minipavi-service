<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Display;

use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Vdt\Screen\Screen;
use MiniPaviService\Vdt\VdtHelper;

/**
 * Horizontal line made of a repeated character (<line> in the page XML format)
 */
final readonly class Line implements Element
{
    // Characters for char="top", "middle" and "bottom" in the configuration
    public const TOP = '_';
    public const MIDDLE = "\x60";
    public const BOTTOM = "\x7E";

    public int $length;

    /**
     * @param ?int $length defaults to the end of the row
     */
    public function __construct(
        public int $row,
        public int $col = 1,
        ?int $length = null,
        public string $char = self::MIDDLE,
        public Color $color = Color::White,
    ) {
        Position::check($row, $col);
        if (strlen($char) != 1) {
            throw new \InvalidArgumentException("A line is made of a single character, got '$char'");
        }
        $this->length = $length ?? Screen::COLS + 1 - $col;
        if ($this->length < 1 || $col + $this->length - 1 > Screen::COLS) {
            throw new \InvalidArgumentException("Line of length $this->length from column $col goes past column " . Screen::COLS);
        }
    }

    public function getContent(Terminal $terminal): string
    {
        return VdtHelper::setPos($this->col, $this->row)->toString() . $this->color->text() . VdtHelper::repeatChar($this->char, $this->length)->toString();
    }
}
