<?php

declare(strict_types=1);

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
     * Color of a number in Videotex, from 0 (black) to 7 (white)
     *
     * @throws \ValueError for another number
     */
    public static function fromIndex(int $index): self
    {
        foreach (self::cases() as $color) {
            if ($color->index() === $index) {
                return $color;
            }
        }

        throw new \ValueError("No color of index {$index}");
    }

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

    /**
     * Number of the color in Videotex, from 0 (black) to 7 (white)
     */
    private function index(): int
    {
        return match ($this) {
            self::Black => 0,
            self::Red => 1,
            self::Green => 2,
            self::Yellow => 3,
            self::Blue => 4,
            self::Magenta => 5,
            self::Cyan => 6,
            self::White => 7,
        };
    }
}
