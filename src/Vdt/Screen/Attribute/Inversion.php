<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen\Attribute;

use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Zone;
use MiniPaviService\Vdt\Videotex;

/**
 * Inverse video, the text and background colours swapped: ESC ] (inverse) or ESC \ (not). It
 * applies at once in text; the Minitel discards it in mosaic.
 */
enum Inversion: string implements Attribute
{
    case Inverse = "\x5D";
    case NoInverse = "\x5C";

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
        return $attributes->withInversion($this);
    }
}
