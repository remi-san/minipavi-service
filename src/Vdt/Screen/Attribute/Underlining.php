<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen\Attribute;

use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Zone;
use MiniPaviService\Vdt\Videotex;

/**
 * Underlining: ESC Z (underlined) or ESC Y (not). In text, a zone attribute, which waits for
 * a delimiter; in mosaic, the separation of the mosaic characters, which applies at once.
 */
enum Underlining: string implements Attribute
{
    case Underline = "\x5A";
    case NoUnderline = "\x59";

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
        return !$attributes->isMosaic();
    }

    public function waitingIn(Zone $zone): Zone
    {
        return $zone->withUnderlineWaiting($this);
    }

    public function appliedTo(Attributes $attributes): Attributes
    {
        return $attributes->withUnderlining($this);
    }
}
