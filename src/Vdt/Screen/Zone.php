<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Vdt\Videotex;

/**
 * The zone attributes, as the Minitel keeps them (MiniPavi's emulator, decoder.js, #28, #30):
 * underline, background colour and masking. In text, they don't apply to the next character:
 * they wait (waits()) for a delimiter, a space drawn while one waits. The delimiter starts a
 * zone, which the cells after it on its row show (Zones): its background up to the next
 * delimiter, mosaic or empty cell, its underline too, and its masking up to the next
 * delimiter. Masking waits in mosaic too; there, the background colour applies at once, and
 * ESC Z separates. A positioning, a home or a clear screen drops what waits, and the zone a
 * delimiter started (none()).
 *
 * The cursor's zone is the one the last delimiter started, with what waits for the next. A
 * cell's (Zones::of()) is what it shows: its background, underline and masking. Immutable.
 */
final readonly class Zone
{
    /**
     * @param bool   $underline         whether the zone is underlined
     * @param Color  $background        the zone's background colour
     * @param bool   $mask              whether the zone is masked
     * @param ?bool  $waitingUnderline  what ESC Z or ESC Y set in text, until a delimiter
     * @param ?Color $waitingBackground what ESC P to ESC W set in text, until a delimiter or SO
     * @param ?bool  $waitingMask       what ESC X or ESC _ set, until a delimiter
     */
    private function __construct(
        private bool $underline,
        private Color $background,
        private bool $mask,
        private ?bool $waitingUnderline = null,
        private ?Color $waitingBackground = null,
        private ?bool $waitingMask = null,
    ) {}

    /**
     * No zone, and nothing waiting: as the Minitel starts, after a positioning, and at the
     * start of each row (black, neither underlined nor masked)
     */
    public static function none(): self
    {
        return new self(underline: false, background: Color::Black, mask: false);
    }

    /**
     * Whether ESC byte, received with these character attributes, sets a zone attribute that
     * waits for a delimiter (with()), rather than a character attribute: masking always,
     * underline and the background colour in text only
     *
     * @param string $byte the byte after ESC
     */
    public static function waits(string $byte, Attributes $attributes): bool
    {
        if (Attributes::isMask($byte)) {
            return true;
        }

        return !$attributes->isMosaic() && (Attributes::isUnderline($byte) || Attributes::isBackground($byte));
    }

    /**
     * After ESC byte, a zone attribute that waits (waits()): it waits for the next delimiter
     *
     * @param string $byte the byte after ESC
     */
    public function with(string $byte): self
    {
        if (Attributes::isMask($byte)) {
            return $this->waiting($this->waitingUnderline, $this->waitingBackground, $byte === Attributes::MASK);
        }
        if (Attributes::isUnderline($byte)) {
            return $this->waiting($byte === Attributes::UNDERLINE, $this->waitingBackground, $this->waitingMask);
        }

        return $this->waiting($this->waitingUnderline, Attributes::backgroundOf($byte), $this->waitingMask);
    }

    /**
     * After SO (mosaic) or SI (text): SO ends the underlined zone, and applies the waiting
     * background colour (attributesAfter()); what else waits still does
     *
     * @param string $byte SI or SO
     */
    public function withCharset(string $byte): self
    {
        if ($byte !== Videotex::MOSAIC_SET) {
            return $this;
        }

        return new self(false, $this->background, $this->mask, $this->waitingUnderline, null, $this->waitingMask);
    }

    /**
     * The character attributes after SO or SI: SO applies the background colour waiting, if
     * one is (setCharType())
     *
     * @param string     $byte       SI or SO
     * @param Attributes $attributes the attributes SO or SI sets (Attributes::withCharset())
     */
    public function attributesAfter(string $byte, Attributes $attributes): Attributes
    {
        if ($byte !== Videotex::MOSAIC_SET || $this->waitingBackground === null) {
            return $attributes;
        }

        return $attributes->with(Attributes::backgroundColor($this->waitingBackground));
    }

    /**
     * Whether a space drawn in text now is a delimiter: a zone attribute waits
     */
    public function isWaiting(): bool
    {
        return $this->waitingUnderline !== null || $this->waitingBackground !== null || $this->waitingMask !== null;
    }

    /**
     * After a delimiter drawn with these character attributes: the zone it starts, with what
     * waited, and for what didn't, the zone's underline and masking before it, and the
     * background colour of the attributes (the last one applied: printDelimiter()). Nothing
     * waits any more
     */
    public function delimited(Attributes $attributes): self
    {
        return new self(
            $this->waitingUnderline ?? $this->underline,
            $this->waitingBackground ?? $attributes->background(),
            $this->waitingMask ?? $this->mask,
        );
    }

    /**
     * What a cell of this background shows in this zone, if it isn't text in it: a mosaic, an
     * empty or a delimiter cell, and the text cells after a mosaic or empty one. Its masking,
     * on that background, not underlined (drawRow())
     */
    public function on(Color $background): self
    {
        return new self(false, $background, $this->mask);
    }

    /**
     * Whether a cell in this zone shows as in that one: the same background, underline and
     * masking
     */
    public function showsAs(self $zone): bool
    {
        return (
            $this->underline === $zone->underline
            && $this->background === $zone->background
            && $this->mask === $zone->mask
        );
    }

    public function background(): Color
    {
        return $this->background;
    }

    /**
     * The character attributes of a cell shown in this zone, set by these: its background and
     * masking, and in text its underline (in mosaic, ESC Z is separation, not the zone's)
     */
    public function shownWith(Attributes $attributes): Attributes
    {
        $shown = $attributes
            ->with(Attributes::backgroundColor($this->background))
            ->with($this->mask ? Attributes::MASK : Attributes::UNMASK);
        if ($attributes->isMosaic()) {
            return $shown;
        }

        return $shown->with($this->underline ? Attributes::UNDERLINE : Attributes::NO_UNDERLINE);
    }

    /**
     * Videotex codes making the next space a delimiter that starts this zone, from what the
     * zone before it and these attributes leave (nothing waiting): ESC P to ESC W, ESC X or
     * ESC _, ESC Z or ESC Y, for what differs; ESC Z or ESC Y alone if nothing does, as one at
     * least must wait
     */
    public function delimiterCodesFrom(self $current, Attributes $attributes): string
    {
        $codes = $this->background === $attributes->background() ? '' : Videotex::backgroundColor($this->background);
        if ($this->mask !== $current->mask) {
            $codes .= $this->mask ? Videotex::MASK_ON : Videotex::MASK_OFF;
        }
        if ($codes === '' || $this->underline !== $current->underline) {
            $codes .= $this->underline ? Videotex::UNDERLINE_ON : Videotex::UNDERLINE_OFF;
        }

        return $codes;
    }

    private function waiting(?bool $underline, ?Color $background, ?bool $mask): self
    {
        return new self($this->underline, $this->background, $this->mask, $underline, $background, $mask);
    }
}
