<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Display;

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
    public function vdt(): string
    {
        return match ($this) {
            self::Normal => Videotex::SIZE_NORMAL,
            self::DoubleHeight => Videotex::SIZE_DOUBLE_HEIGHT,
            self::DoubleWidth => Videotex::SIZE_DOUBLE_WIDTH,
            self::Double => Videotex::SIZE_DOUBLE,
        };
    }

    /**
     * Number of columns taken by a character of this size
     */
    public function width(): int
    {
        return match ($this) {
            self::DoubleWidth, self::Double => 2,
            default => 1,
        };
    }
}
