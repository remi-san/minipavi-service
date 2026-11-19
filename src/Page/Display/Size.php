<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Display;

use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Vdt;
use MiniPaviService\Vdt\Videotex;

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
        return new Vdt(Videotex::ESC . $this->attribute());
    }

    /**
     * Number of columns taken by a character of this size (Attributes::width())
     */
    public function width(): int
    {
        return Attributes::defaults()->with($this->attribute())->width();
    }

    /**
     * The byte after ESC setting this size (Screen\Attributes)
     */
    private function attribute(): string
    {
        return match ($this) {
            self::Normal => Attributes::SIZE_NORMAL,
            self::DoubleHeight => Attributes::DOUBLE_HEIGHT,
            self::DoubleWidth => Attributes::DOUBLE_WIDTH,
            self::Double => Attributes::DOUBLE,
        };
    }
}
