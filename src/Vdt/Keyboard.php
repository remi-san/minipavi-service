<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

/**
 * Case of the letters typed on the Minitel (PRO2 sequence), by its name in the page XML
 * format (keyboard="lowercase"): the Minitel starts in uppercase
 */
enum Keyboard: string
{
    case Uppercase = 'uppercase';
    case Lowercase = 'lowercase';

    /**
     * @return non-empty-string
     */
    public function sequence(): string
    {
        return match ($this) {
            self::Uppercase => "\x1B\x3A\x6A\x45",
            self::Lowercase => "\x1B\x3A\x69\x45",
        };
    }
}
