<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

/**
 * Whether the screen scrolls up past the last row (PRO2 sequence), by its name in the page XML
 * format (scroll="off"): off, page mode
 */
enum Scroll: string
{
    case On = 'on';
    case Off = 'off';

    /**
     * @return non-empty-string
     */
    public function sequence(): string
    {
        return match ($this) {
            self::On => "\x1B\x3A\x69\x43",
            self::Off => "\x1B\x3A\x6A\x43",
        };
    }
}
