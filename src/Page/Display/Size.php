<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Display;

use MiniPaviService\Vdt\Screen\Attribute\CharacterSize;
use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Vdt;

/**
 * Character sizes, named as in the page XML format (size="double-height")
 */
enum Size: string
{
    case Normal = 'normal';
    case DoubleHeight = 'double-height';
    case DoubleWidth = 'double-width';
    case Double = 'double';

    /**
     * Videotex attribute for this size
     */
    public function toVdt(): Vdt
    {
        return new Vdt($this->attribute()->code());
    }

    /**
     * Number of columns taken by a character of this size (Attributes::width())
     */
    public function width(): int
    {
        return Attributes::defaults()->with($this->attribute())->width();
    }

    /**
     * The attribute setting this size
     */
    private function attribute(): CharacterSize
    {
        return match ($this) {
            self::Normal => CharacterSize::Normal,
            self::DoubleHeight => CharacterSize::DoubleHeight,
            self::DoubleWidth => CharacterSize::DoubleWidth,
            self::Double => CharacterSize::Double,
        };
    }
}
