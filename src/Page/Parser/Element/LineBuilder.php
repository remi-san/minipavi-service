<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser\Element;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Minitel\Span;
use MiniPaviService\Page\Building\AttributeValues;
use MiniPaviService\Page\Building\ElementBuilder;
use MiniPaviService\Page\Building\Placement;
use MiniPaviService\Page\Display\Element;
use MiniPaviService\Page\Display\Line;

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

    public function checkPlacement(Placement $placement): void {}

    public function build(AttributeValues $attributes, string $text, Placement $placement): Element
    {
        $attributes = $placement->placeByRow($attributes);
        $char = $attributes->string('char', default: 'middle');

        return new Line(
            new Span($attributes->position(), $attributes->optionalInt('length')),
            char: self::CHARS[$char] ?? $char,
            color: $attributes->enum('color', ...Color::cases()) ?? Color::White,
        );
    }
}
