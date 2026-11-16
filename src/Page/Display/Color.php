<?php

namespace MiniPaviService\Page\Display;

/**
 * Minitel colors, named as in the page XML format (color="red")
 */
enum Color: string
{
    case Black = 'black';
    case Red = 'red';
    case Green = 'green';
    case Yellow = 'yellow';
    case Blue = 'blue';
    case Magenta = 'magenta';
    case Cyan = 'cyan';
    case White = 'white';

    /**
     * Videotex attribute for this text (foreground) color
     */
    public function text(): string
    {
        return "\x1B" . chr(0x40 + $this->index());
    }

    /**
     * Videotex attribute for this background color
     */
    public function background(): string
    {
        return "\x1B" . chr(0x50 + $this->index());
    }

    private function index(): int
    {
        return array_search($this, self::cases(), true);
    }
}
