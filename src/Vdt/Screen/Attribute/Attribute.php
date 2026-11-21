<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen\Attribute;

use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Zone;

/**
 * An attribute Videotex content sets with ESC and a byte (code()), which Videotex's codes are
 * made of: a colour of the text or of the background, a size, blinking, masking, underlining
 * or inverse video (AttributeReader reads it from its byte). It knows what it does on the
 * screen: where the Minitel discards it (appliesOn(), appliesInMosaic()), whether it waits
 * for a delimiter (waitsIn(), waitingIn()), and how it changes the character attributes
 * (appliedTo()). Cursor::setAttribute() asks it each.
 */
interface Attribute
{
    /**
     * ESC and the byte setting this attribute
     */
    public function code(): string;

    /**
     * Whether the Minitel applies this attribute on row: it discards double height (ESC M
     * and ESC O) on rows 0 and 1 (Attributes::allowsDoubleHeight())
     */
    public function appliesOn(int $row): bool;

    /**
     * Whether the Minitel applies this attribute in mosaic: it discards a size and inverse
     * video there (MiniPavi's emulator, setSize() and setInvert())
     */
    public function appliesInMosaic(): bool;

    /**
     * Whether this attribute, received with these character attributes, is a zone attribute
     * that waits for a delimiter (waitingIn()), rather than a character attribute
     * (appliedTo()): masking always, underline and the background colour in text only (Zone)
     */
    public function waitsIn(Attributes $attributes): bool;

    /**
     * The zone with this attribute waiting in it for the next delimiter, when it waits for one
     * (waitsIn()); one that never waits leaves the zone as it is
     */
    public function waitingIn(Zone $zone): Zone;

    /**
     * These character attributes, with this one set
     */
    public function appliedTo(Attributes $attributes): Attributes;
}
