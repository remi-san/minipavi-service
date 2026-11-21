<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen\Attribute;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Zone;
use MiniPaviService\Vdt\Videotex;

/**
 * The background colour: ESC P to ESC W, 0x50 + the colour's index (Color::index()). In text,
 * a zone attribute, which waits for a delimiter (or SO); in mosaic, it applies at once.
 */
final readonly class BackgroundColor implements Attribute
{
    private const FIRST = 0x50;

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
        return !$attributes->isMosaic();
    }

    public function waitIn(Zone $zone): Zone
    {
        return $zone->withBackgroundWaiting($this->color);
    }

    public function appliedTo(Attributes $attributes): Attributes
    {
        return $attributes->withBackground($this->color);
    }
}
