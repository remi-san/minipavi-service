<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Display;

use MiniPaviService\Minitel\Span;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Vdt\Vdt;

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
            throw new \InvalidArgumentException("A line is made of a single character, got '{$char}'");
        }
    }

    public function getContent(Terminal $terminal): string
    {
        return (
            Vdt::position($this->span->start->col, $this->span->start->row)->toString()
            . $this->color->text()
            . Vdt::repeat($this->char, $this->span->length)->toString()
        );
    }
}
