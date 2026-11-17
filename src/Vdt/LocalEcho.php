<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

/**
 * Whether the Minitel shows what is typed itself (PRO3 sequence), by its name in the page XML
 * format (echo="off"): off when the gateway's input commands show it
 */
enum LocalEcho: string
{
    case On = 'on';
    case Off = 'off';

    /**
     * @return non-empty-string
     */
    public function sequence(): string
    {
        return match ($this) {
            self::On => "\x1B\x3B\x61\x58\x51",
            self::Off => "\x1B\x3B\x60\x58\x51",
        };
    }
}
