<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser\Element;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Page\Building\AttributeValues;
use MiniPaviService\Page\Building\ElementBuilder;
use MiniPaviService\Page\Building\Placement;
use MiniPaviService\Page\Display\Element;
use MiniPaviService\Page\Display\Size;
use MiniPaviService\Page\Display\Text;

/**
 * <text row col align color bg size fill>: text at a position, or centered on its row
 */
final readonly class TextBuilder implements ElementBuilder
{
    public function tag(): string
    {
        return 'text';
    }

    public function checkPlacement(Placement $placement): void {}

    public function build(AttributeValues $attributes, string $text, Placement $placement): Element
    {
        $attributes = $placement->placeByRow($attributes);
        return new Text(
            $text,
            $attributes->position(),
            centered: $attributes->choice('align', values: ['left' => false, 'center' => true], default: false),
            color: $attributes->enum('color', ...Color::cases()) ?? Color::White,
            bg: $attributes->enum('bg', ...Color::cases()),
            size: $attributes->enum('size', ...Size::cases()),
            fill: $attributes->choice('fill', values: ['yes' => true, 'no' => false], default: false),
        );
    }
}
