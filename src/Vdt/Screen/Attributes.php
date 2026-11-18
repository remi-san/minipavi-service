<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

use MiniPaviService\Page\Display\Color;
use MiniPaviService\Vdt\Videotex;

/**
 * Character attributes of a cell, as Videotex content sets them: the charset (text or mosaic),
 * the colours, the size, blinking, masking, underlining and inverse video. A positioning (US),
 * a home (RS) or a clear screen (FF) sets them back to defaults(). Immutable.
 */
final readonly class Attributes
{
    // SI and SO: the charsets
    private const TEXT = "\x0F";
    private const MOSAIC = "\x0E";

    // The byte after ESC of each group, as the Minitel starts (the charset: SI)
    private const DEFAULTS = [
        'charset' => self::TEXT,
        'fg' => "\x47",
        'bg' => "\x50",
        'size' => "\x4C",
        'blink' => "\x49",
        'mask' => "\x5F",
        'underline' => "\x59",
        'inverse' => "\x5C",
    ];

    /**
     * @param array<string, string> $values group => the byte after ESC (SI or SO for the charset)
     */
    private function __construct(
        private array $values,
    ) {}

    /**
     * Attributes of the Minitel as it starts: white text on black, normal size
     */
    public static function defaults(): self
    {
        return new self(self::DEFAULTS);
    }

    /**
     * Whether ESC <byte> sets an attribute (with()), rather than being another sequence
     */
    public static function isAttribute(string $byte): bool
    {
        return self::group($byte) !== null;
    }

    /**
     * These attributes, with the one set by ESC <byte>; unchanged if it doesn't set one
     */
    public function with(string $byte): self
    {
        $group = self::group($byte);

        return $group === null ? $this : new self([$group => $byte] + $this->values);
    }

    /**
     * These attributes, in the charset SO (mosaic) or SI (text) selects
     */
    public function withCharset(string $byte): self
    {
        return new self(['charset' => $byte === self::MOSAIC ? self::MOSAIC : self::TEXT] + $this->values);
    }

    public function isMosaic(): bool
    {
        return $this->values['charset'] === self::MOSAIC;
    }

    public function foreground(): Color
    {
        return Color::fromIndex(\ord($this->values['fg']) - 0x40);
    }

    public function background(): Color
    {
        return Color::fromIndex(\ord($this->values['bg']) - 0x50);
    }

    public function isDoubleHeight(): bool
    {
        return \in_array($this->values['size'], ["\x4D", "\x4F"], strict: true);
    }

    public function isDoubleWidth(): bool
    {
        return \in_array($this->values['size'], ["\x4E", "\x4F"], strict: true);
    }

    public function isBlinking(): bool
    {
        return $this->values['blink'] === "\x48";
    }

    public function isMasked(): bool
    {
        return $this->values['mask'] === "\x58";
    }

    public function isUnderlined(): bool
    {
        return $this->values['underline'] === "\x5A";
    }

    public function isInverse(): bool
    {
        return $this->values['inverse'] === "\x5D";
    }

    /**
     * Whether ESC M (double height) and ESC O (double size) are accepted on row: not on rows
     * 0 and 1, where the Minitel discards them (checked in MiniPavi's emulator, #172)
     */
    public static function allowsDoubleHeight(int $row): bool
    {
        return $row > 1;
    }

    /**
     * Whether ESC byte sets double height: ESC M (double height) or ESC O (double size)
     */
    public static function isTallSize(string $byte): bool
    {
        return \in_array($byte, [Videotex::SIZE_DOUBLE_HEIGHT[1], Videotex::SIZE_DOUBLE[1]], strict: true);
    }

    /**
     * These attributes, with a size that can be set on row: where ESC M and ESC O would be
     * discarded, double height becomes normal size and double size double width (ESC N is
     * accepted there), so that a character written there takes the columns it is given
     */
    public function writableOn(int $row): self
    {
        if (self::allowsDoubleHeight($row) || !$this->isDoubleHeight()) {
            return $this;
        }

        return new self([
            'size' => $this->isDoubleWidth() ? Videotex::SIZE_DOUBLE_WIDTH[1] : self::DEFAULTS['size'],
        ] + $this->values);
    }

    /**
     * Columns taken by a character: double width only applies to text characters
     */
    public function width(): int
    {
        return $this->isDoubleWidth() && !$this->isMosaic() ? 2 : 1;
    }

    /**
     * Videotex codes setting these attributes where current ones are: the charset (SI or SO),
     * then ESC <byte> for each other attribute that differs; '' if none does
     */
    public function codesFrom(self $current): string
    {
        $codes = $this->values['charset'] !== $current->values['charset'] ? $this->values['charset'] : '';
        foreach (self::DEFAULTS as $group => $_) {
            if ($group === 'charset' || $this->values[$group] === $current->values[$group]) {
                continue;
            }
            $codes .= "\x1B" . $this->values[$group];
        }

        return $codes;
    }

    /**
     * Group of the attribute set by ESC <byte>, or null if it is not one
     */
    private static function group(string $byte): ?string
    {
        $code = \ord($byte);

        return match (true) {
            $code >= 0x40 && $code <= 0x47 => 'fg',
            $code >= 0x50 && $code <= 0x57 => 'bg',
            $code >= 0x4C && $code <= 0x4F => 'size',
            $code === 0x48 || $code === 0x49 => 'blink',
            $code === 0x58 || $code === 0x5F => 'mask',
            $code === 0x59 || $code === 0x5A => 'underline',
            $code === 0x5C || $code === 0x5D => 'inverse',
            default => null,
        };
    }
}
