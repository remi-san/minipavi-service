<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Vdt\Videotex;

/**
 * Character shown in a cell of the Screen, with its attributes (see Attributes), and the part
 * of it the cell shows (Part: a half, or a quarter, of a character bigger than a cell)
 */
final readonly class Cell
{
    public function __construct(
        public string $bytes,
        public Attributes $attributes,
        public Part $part,
    ) {}

    /**
     * This cell as shown in zone (ScreenCells, Zones::of()): on its background, masked if it
     * is, and in text underlined if it is (Zone::shownWith())
     */
    public function inZone(Zone $zone): self
    {
        return new self($this->bytes, $zone->shownWith($this->attributes), $this->part);
    }

    /**
     * The character as text (UTF-8), as the Minitel shows it: G0 characters as in ASCII, but
     * those it draws otherwise (G0Characters: e.g. ▔ for 0x7E, a line across the top), G2
     * ones (after SS2 or SYN) as their character (G2Characters: _ for one it doesn't have),
     * mosaic cells as # when they have pixels or a background color, a space otherwise
     */
    public function text(): string
    {
        if ($this->attributes->isMosaic()) {
            return $this->bytes === ' ' && $this->attributes->background() === Color::Black ? ' ' : '#';
        }
        if ($this->bytes === Videotex::SUB) {
            return '?';
        }

        return G2Characters::starts($this->bytes[0])
            ? G2Characters::text($this->bytes)
            : G0Characters::text($this->bytes);
    }
}
