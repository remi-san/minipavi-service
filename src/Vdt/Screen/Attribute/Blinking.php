<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen\Attribute;

use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Zone;
use MiniPaviService\Vdt\Videotex;

/**
 * Blinking: ESC H (blinking) or ESC I (fixed). It applies at once, in text as in mosaic.
 */
enum Blinking: string implements Attribute
{
    case Blink = "\x48";
    case Fixed = "\x49";

    public function code(): string
    {
        return Videotex::ESC . $this->value;
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
        return $attributes->withBlinking($this);
    }
}
