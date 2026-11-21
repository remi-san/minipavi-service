<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen\Attribute;

use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Zone;
use MiniPaviService\Vdt\Videotex;

/**
 * Masking, hiding what is drawn until revealed: ESC X (masked) or ESC _ (unmasked). A zone
 * attribute, in text as in mosaic: it waits for a delimiter.
 */
enum Masking: string implements Attribute
{
    case Mask = "\x58";
    case Unmask = "\x5F";

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
        return true;
    }

    public function waitIn(Zone $zone): Zone
    {
        return $zone->withMaskWaiting($this);
    }

    public function appliedTo(Attributes $attributes): Attributes
    {
        return $attributes->withMasking($this);
    }
}
