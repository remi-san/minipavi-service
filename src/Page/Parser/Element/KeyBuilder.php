<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser\Element;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Page\Display\Element;
use MiniPaviService\Page\Display\Key;
use MiniPaviService\Page\Parser\AttributeValues;

/**
 * <key row col color bg>Label</key>: a function key badge
 */
final readonly class KeyBuilder implements ElementBuilder
{
    public function tag(): string
    {
        return 'key';
    }

    public function build(AttributeValues $attributes, string $text): Element
    {
        return new Key(
            $text,
            new Position($attributes->int('row', default: 1), $attributes->int('col', default: 1)),
            color: $attributes->enum('color', Color::class) ?? Color::Green,
            bg: $attributes->enum('bg', Color::class),
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
