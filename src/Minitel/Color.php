<?php

declare(strict_types=1);

namespace MiniPaviService\Minitel;

use MiniPaviService\InvalidValueException;

/**
 * The Minitel's 8 colors, named as in the page XML format (color="red"), each with its number
 * in Videotex (index())
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
     * @throws InvalidValueException for another number
     */
    public static function fromIndex(int $index): self
    {
        foreach (self::cases() as $color) {
            if ($color->index() === $index) {
                return $color;
            }
        }

        throw new InvalidValueException("No color of index {$index}");
    }

    /**
     * Number of the color in Videotex, from 0 (black) to 7 (white)
     */
    public function index(): int
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
