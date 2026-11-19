<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

use MiniPaviService\Vdt\Videotex;

/**
 * The zone attributes of the cursor, as the Minitel keeps them (MiniPavi's emulator,
 * decoder.js, #28). In text, underline (ESC Z, ESC Y), as background colour and masking,
 * doesn't apply to the next character: it waits for a delimiter, a space drawn while a zone
 * attribute waits. The delimiter starts a zone, which runs along its row up to the next
 * delimiter, mosaic or empty cell (ScreenCells). A positioning, a home or a clear screen drops
 * what waits, and the zone a delimiter started (none()).
 *
 * The framework still applies background colour and masking at once (Attributes): it follows
 * them here only as they make a space a delimiter, that carries the zone on. Immutable.
 */
final readonly class Zone
{
    /**
     * @param bool  $underline        whether the zone the last delimiter started is underlined
     * @param ?bool $waitingUnderline what ESC Z or ESC Y set in text, until a delimiter; null
     *                                if neither did
     */
    private function __construct(
        private bool $underline,
        private ?bool $waitingUnderline,
        private bool $waitingBackground,
        private bool $waitingMask,
    ) {}

    /**
     * No zone, and nothing waiting: as the Minitel starts, and after a positioning
     */
    public static function none(): self
    {
        return new self(underline: false, waitingUnderline: null, waitingBackground: false, waitingMask: false);
    }

    /**
     * After ESC byte, received with these character attributes: masking waits in mosaic and in
     * text, underline and the background colour in text only (in mosaic, ESC Z separates, and
     * the background applies at once)
     *
     * @param string $byte the byte after ESC
     */
    public function with(string $byte, Attributes $attributes): self
    {
        if (Attributes::isMask($byte)) {
            return new self($this->underline, $this->waitingUnderline, $this->waitingBackground, waitingMask: true);
        }
        if ($attributes->isMosaic()) {
            return $this;
        }
        if (Attributes::isUnderline($byte)) {
            return new self(
                $this->underline,
                waitingUnderline: $byte === Attributes::UNDERLINE,
                waitingBackground: $this->waitingBackground,
                waitingMask: $this->waitingMask,
            );
        }
        if (Attributes::isBackground($byte)) {
            return new self(
                $this->underline,
                $this->waitingUnderline,
                waitingBackground: true,
                waitingMask: $this->waitingMask,
            );
        }

        return $this;
    }

    /**
     * After SO (mosaic) or SI (text): SO ends the underlined zone, and applies the waiting
     * background colour (setCharType()); what else waits still does
     *
     * @param string $byte SI or SO
     */
    public function withCharset(string $byte): self
    {
        if ($byte !== Videotex::MOSAIC_SET) {
            return $this;
        }

        return new self(false, $this->waitingUnderline, waitingBackground: false, waitingMask: $this->waitingMask);
    }

    /**
     * Whether a space drawn in text now is a delimiter: a zone attribute waits
     */
    public function isWaiting(): bool
    {
        return $this->waitingUnderline !== null || $this->waitingBackground || $this->waitingMask;
    }

    /**
     * After a delimiter: the zone it starts, underlined as ESC Z or ESC Y set it (as the zone
     * before it if neither did), and nothing waiting
     */
    public function delimited(): self
    {
        return new self(
            $this->waitingUnderline ?? $this->underline,
            null,
            waitingBackground: false,
            waitingMask: false,
        );
    }

    /**
     * Whether the zone the last delimiter started is underlined
     */
    public function isUnderlined(): bool
    {
        return $this->underline;
    }

    /**
     * Videotex codes making the next space a delimiter that starts this zone, whatever else
     * waits: ESC Z if it is underlined, ESC Y if not
     */
    public function delimiterCodes(): string
    {
        return $this->underline ? Videotex::UNDERLINE_ON : Videotex::UNDERLINE_OFF;
    }
}
