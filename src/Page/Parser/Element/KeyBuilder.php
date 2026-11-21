<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser\Element;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Page\Building\AttributeValues;
use MiniPaviService\Page\Building\ElementBuilder;
use MiniPaviService\Page\Building\Placement;
use MiniPaviService\Page\Display\Element;
use MiniPaviService\Page\Display\Key;

/**
 * <key row col color bg>Label</key>: a function key badge
 */
final readonly class KeyBuilder implements ElementBuilder
{
    public function tag(): string
    {
        return 'key';
    }

    public function checkPlacement(Placement $placement): void {}

    public function build(AttributeValues $attributes, string $text, Placement $placement): Element
    {
        $attributes = $placement->placeByRow($attributes);
        return new Key(
            $text,
            $attributes->position(),
            color: $attributes->enum('color', ...Color::cases()) ?? Color::Green,
            bg: $attributes->enum('bg', ...Color::cases()),
        );
    }
}
