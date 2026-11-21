<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen\Attribute;

use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Zone;
use MiniPaviService\Vdt\Videotex;

/**
 * The size of the characters: ESC L (normal), M (double height), N (double width) or O
 * (double size). The Minitel discards it in mosaic, and double height on rows 0 and 1.
 *
 * isTall() and isWide() each match over the cases: what each size is, said by the size
 * itself.
 */
enum CharacterSize: string implements Attribute
{
    case Normal = "\x4C";
    case DoubleHeight = "\x4D";
    case DoubleWidth = "\x4E";
    case Double = "\x4F";

    /**
     * Whether a character of this size takes 2 rows: double height or double size
     */
    public function isTall(): bool
    {
        return match ($this) {
            self::DoubleHeight, self::Double => true,
            self::Normal, self::DoubleWidth => false,
        };
    }

    /**
     * Whether a character of this size takes 2 columns: double width or double size
     */
    public function isWide(): bool
    {
        return match ($this) {
            self::DoubleWidth, self::Double => true,
            self::Normal, self::DoubleHeight => false,
        };
    }

    /**
     * This size in a single row: double height becomes normal size, and double size double
     * width
     */
    public function flattened(): self
    {
        return $this->isWide() ? self::DoubleWidth : self::Normal;
    }

    public function code(): string
    {
        return Videotex::ESC . $this->value;
    }

    public function appliesOn(int $row): bool
    {
        return !$this->isTall() || Attributes::allowsDoubleHeight($row);
    }

    public function appliesInMosaic(): bool
    {
        return false;
    }

    public function waitsIn(Attributes $attributes): bool
    {
        return false;
    }

    public function waitingIn(Zone $zone): Zone
    {
        return $zone;
    }

    public function appliedTo(Attributes $attributes): Attributes
    {
        return $attributes->withSize($this);
    }
}
