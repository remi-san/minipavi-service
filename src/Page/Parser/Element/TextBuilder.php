<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser\Element;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Page\Display\Element;
use MiniPaviService\Page\Display\Size;
use MiniPaviService\Page\Display\Text;
use MiniPaviService\Page\Parser\AttributeValues;

/**
 * <text row col align color bg size fill>: text at a position, or centered on its row
 */
final readonly class TextBuilder implements ElementBuilder
{
    public function tag(): string
    {
        return 'text';
    }

    public function build(AttributeValues $attributes, string $text): Element
    {
        return new Text(
            $text,
            $attributes->position(),
            centered: $attributes->choice('align', values: ['left' => false, 'center' => true], default: false),
            color: $attributes->enum('color', Color::class) ?? Color::White,
            bg: $attributes->enum('bg', Color::class),
            size: $attributes->enum('size', Size::class),
            fill: $attributes->choice('fill', values: ['yes' => true, 'no' => false], default: false),
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
