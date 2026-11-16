<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Display;

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
            self::Normal => VDT_SZNORM,
            self::DoubleHeight => VDT_SZDBLH,
            self::DoubleWidth => VDT_SZDBLW,
            self::Double => VDT_SZDBLHW,
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
