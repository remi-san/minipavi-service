<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser\Element;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\Span;
use MiniPaviService\Page\Display\Element;
use MiniPaviService\Page\Display\Line;
use MiniPaviService\Page\Parser\AttributeValues;

/**
 * <line row col length char color/>: a horizontal line of a repeated character (char: top,
 * middle, bottom, or the character itself)
 */
final readonly class LineBuilder implements ElementBuilder
{
    private const CHARS = ['top' => Line::TOP, 'middle' => Line::MIDDLE, 'bottom' => Line::BOTTOM];

    public function tag(): string
    {
        return 'line';
    }

    public function build(AttributeValues $attributes, string $text): Element
    {
        $char = $attributes->string('char', default: 'middle');

        return new Line(
            new Span(
                new Position($attributes->int('row', default: 1), $attributes->int('col', default: 1)),
                $attributes->optionalInt('length'),
            ),
            char: self::CHARS[$char] ?? $char,
            color: $attributes->enum('color', Color::class) ?? Color::White,
        );
    }

    public function isPlacedByRow(): bool
    {
        return true;
    }

    public function isAllowedInLoop(): bool
    {
        return true;
    }
}
