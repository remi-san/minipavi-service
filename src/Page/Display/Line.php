<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Display;

use MiniPaviService\InvalidValueException;
use MiniPaviService\Minitel\Color;
use MiniPaviService\Minitel\Span;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Vdt\Vdt;
use MiniPaviService\Vdt\Videotex;

/**
 * Horizontal line made of a repeated character (<line> in the page XML format)
 */
final readonly class Line implements Element
{
    // Characters for char="top", "middle" and "bottom" in the configuration
    public const TOP = '_';
    public const MIDDLE = "\x60";
    public const BOTTOM = "\x7E";

    public function __construct(
        public Span $span,
        public string $char = self::MIDDLE,
        public Color $color = Color::White,
    ) {
        if (strlen($char) !== 1) {
            throw new InvalidValueException("A line is made of a single character, got '{$char}'");
        }
    }

    public function toVdt(Terminal $terminal): Vdt
    {
        return Vdt::join(
            Vdt::position($this->span->start->col, $this->span->start->row),
            new Vdt(Videotex::textColor($this->color)),
            Vdt::repeat($this->char, $this->span->length),
        );
    }
}
