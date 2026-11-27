<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Vdt\Screen\Attribute\Attribute;
use MiniPaviService\Vdt\Screen\Attribute\BackgroundColor;
use MiniPaviService\Vdt\Screen\Attribute\Charset;
use MiniPaviService\Vdt\Screen\Attribute\Masking;
use MiniPaviService\Vdt\Screen\Attribute\Underlining;

/**
 * The zone attributes, as the Minitel keeps them (MiniPavi's emulator, decoder.js, #28, #30):
 * underline, background colour and masking. In text, they don't apply to the next character:
 * they wait (Attribute::waitsIn()) for a delimiter, a space drawn while one waits. The
 * delimiter starts a zone, which the cells after it on its row show (Zones): its background
 * up to the next delimiter, mosaic or empty cell, its underline too, and its masking up to
 * the next delimiter. Masking waits in mosaic too; there, the background colour applies at
 * once, and ESC Z separates. A positioning, a home or a clear screen drops what waits, and
 * the zone a delimiter started (none()).
 *
 * The cursor's zone is the one the last delimiter started, with what waits for the next. A
 * cell's (Zones::of()) is what it shows: its background, underline and masking. Immutable.
 */
final readonly class Zone
{
    /**
     * @param Underlining  $underline         whether the zone is underlined
     * @param Color        $background        the zone's background colour
     * @param Masking      $mask              whether the zone is masked
     * @param ?Underlining $waitingUnderline  what ESC Z or ESC Y set in text, until a delimiter
     * @param ?Color       $waitingBackground what ESC P to ESC W set in text, until a delimiter
     *                                        or SO
     * @param ?Masking     $waitingMask       what ESC X or ESC _ set, until a delimiter
     */
    private function __construct(
        private Underlining $underline,
        private Color $background,
        private Masking $mask,
        private ?Underlining $waitingUnderline = null,
        private ?Color $waitingBackground = null,
        private ?Masking $waitingMask = null,
    ) {}

    /**
     * No zone, and nothing waiting: as the Minitel starts, after a positioning, and at the
     * start of each row (black, neither underlined nor masked)
     */
    public static function none(): self
    {
        return new self(Underlining::NoUnderline, Color::Black, Masking::Unmask);
    }

    /**
     * This zone, with masking waiting for the next delimiter (Masking::waitingIn())
     */
    public function withMaskWaiting(Masking $mask): self
    {
        return $this->waiting($this->waitingUnderline, $this->waitingBackground, $mask);
    }

    /**
     * This zone, with underlining waiting for the next delimiter (Underlining::waitingIn())
     */
    public function withUnderlineWaiting(Underlining $underline): self
    {
        return $this->waiting($underline, $this->waitingBackground, $this->waitingMask);
    }

    /**
     * This zone, with a background colour waiting for the next delimiter, or SO
     * (BackgroundColor::waitingIn())
     */
    public function withBackgroundWaiting(Color $background): self
    {
        return $this->waiting($this->waitingUnderline, $background, $this->waitingMask);
    }

    /**
     * After SO (mosaic) or SI (text): SO ends the underlined zone, and applies the waiting
     * background colour (attributesAfter()); what else waits still does
     */
    public function withCharset(Charset $charset): self
    {
        if ($charset !== Charset::Mosaic) {
            return $this;
        }

        return new self(
            Underlining::NoUnderline,
            $this->background,
            $this->mask,
            $this->waitingUnderline,
            null,
            $this->waitingMask,
        );
    }

    /**
     * The character attributes after SO or SI: SO applies the background colour waiting, if
     * one is (setCharType())
     *
     * @param Attributes $attributes the attributes SO or SI sets (Attributes::withCharset())
     */
    public function attributesAfter(Charset $charset, Attributes $attributes): Attributes
    {
        if ($charset !== Charset::Mosaic || $this->waitingBackground === null) {
            return $attributes;
        }

        return $attributes->withBackground($this->waitingBackground);
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
        return new self(Underlining::NoUnderline, $background, $this->mask);
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
        $shown = $attributes->withBackground($this->background)->withMasking($this->mask);
        if ($attributes->isMosaic()) {
            return $shown;
        }

        return $shown->withUnderlining($this->underline);
    }

    /**
     * The zone attributes making the next space a delimiter that starts this zone, from what
     * the zone before it and these attributes leave (nothing waiting): its background colour,
     * masking and underlining, for what differs, in that order; underlining alone if nothing
     * does, as one at least must wait
     *
     * @return list<Attribute>
     */
    public function delimiterAttributesFrom(self $current, Attributes $attributes): array
    {
        $sent = [];
        if ($this->background !== $attributes->background()) {
            $sent[] = new BackgroundColor($this->background);
        }
        if ($this->mask !== $current->mask) {
            $sent[] = $this->mask;
        }
        if ($sent === [] || $this->underline !== $current->underline) {
            $sent[] = $this->underline;
        }

        return $sent;
    }

    private function waiting(?Underlining $underline, ?Color $background, ?Masking $mask): self
    {
        return new self($this->underline, $this->background, $this->mask, $underline, $background, $mask);
    }
}
