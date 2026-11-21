<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen\Attribute;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Zone;
use MiniPaviService\Vdt\Videotex;

/**
 * The text (foreground) colour: ESC @ to ESC G, 0x40 + the colour's index (Color::index()).
 * It applies at once, in text as in mosaic.
 */
final readonly class TextColor implements Attribute
{
    private const FIRST = 0x40;

    public function __construct(
        private Color $color,
    ) {}

    public function code(): string
    {
        return Videotex::ESC . \chr(self::FIRST + $this->color->index());
    }

    public function appliesOn(int $row): bool
    {
        return true;
    }

    public function appliesInMosaic(): bool
    {
        return true;
    }

    public function waitsIn(Attributes $attributes): bool
    {
        return false;
    }

    public function waitIn(Zone $zone): Zone
    {
        return $zone;
    }

    public function appliedTo(Attributes $attributes): Attributes
    {
        return $attributes->withForeground($this->color);
    }
}
