<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Vdt\Videotex;

/**
 * Character attributes of a cell, as Videotex content sets them: the charset (text or mosaic),
 * the colours, the size, blinking, masking, underlining and inverse video. A positioning (US),
 * a home (RS) or a clear screen (FF) sets them back to defaults(). Immutable.
 */
final readonly class Attributes
{
    // The byte after ESC of each attribute, which Videotex's codes are made of
    public const SIZE_NORMAL = "\x4C";
    public const DOUBLE_HEIGHT = "\x4D";
    public const DOUBLE_WIDTH = "\x4E";
    public const DOUBLE = "\x4F";
    public const BLINK = "\x48";
    public const FIXED = "\x49";
    public const MASK = "\x58";
    public const UNMASK = "\x5F";
    public const UNDERLINE = "\x5A";
    public const NO_UNDERLINE = "\x59";
    public const INVERSE = "\x5D";
    public const NO_INVERSE = "\x5C";

    // The colors: the byte after ESC is TEXT_COLOR + the color's index for the text, and
    // BACKGROUND_COLOR + its index for the background (Color::index(), 0 to 7)
    private const TEXT_COLOR = 0x40;
    private const BACKGROUND_COLOR = 0x50;

    // The byte after ESC of each group, as the Minitel starts (the charset: SI): white text
    // (TEXT_COLOR + 7) on black (BACKGROUND_COLOR + 0)
    private const DEFAULTS = [
        'charset' => Videotex::TEXT_SET,
        'fg' => "\x47",
        'bg' => "\x50",
        'size' => self::SIZE_NORMAL,
        'blink' => self::FIXED,
        'mask' => self::UNMASK,
        'underline' => self::NO_UNDERLINE,
        'inverse' => self::NO_INVERSE,
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
     * These attributes, in the charset SO (mosaic) or SI (text) selects, as the Minitel sets
     * them on every SO and SI, even one that doesn't change the charset (MiniPavi's emulator,
     * setCharType(), #26): normal size, no inverse video, no separated mosaic, and on SO no
     * underline. Underline kept by an SI is the text's: in mosaic, ESC Z separates instead
     * (and SO already turned underline off)
     */
    public function withCharset(string $byte): self
    {
        $keepsUnderline = $byte === Videotex::TEXT_SET && !$this->isMosaic();

        return new self([
            'charset' => $byte === Videotex::MOSAIC_SET ? Videotex::MOSAIC_SET : Videotex::TEXT_SET,
            'size' => self::SIZE_NORMAL,
            'inverse' => self::NO_INVERSE,
            'underline' => $keepsUnderline ? $this->values['underline'] : self::NO_UNDERLINE,
        ] + $this->values);
    }

    public function isMosaic(): bool
    {
        return $this->values['charset'] === Videotex::MOSAIC_SET;
    }

    public function foreground(): Color
    {
        return Color::fromIndex(\ord($this->values['fg']) - self::TEXT_COLOR);
    }

    public function background(): Color
    {
        return Color::fromIndex(\ord($this->values['bg']) - self::BACKGROUND_COLOR);
    }

    /**
     * The byte after ESC setting this text (foreground) color
     */
    public static function textColor(Color $color): string
    {
        return \chr(self::TEXT_COLOR + $color->index());
    }

    /**
     * The byte after ESC setting this background color
     */
    public static function backgroundColor(Color $color): string
    {
        return \chr(self::BACKGROUND_COLOR + $color->index());
    }

    public function isDoubleHeight(): bool
    {
        return \in_array($this->values['size'], [self::DOUBLE_HEIGHT, self::DOUBLE], strict: true);
    }

    public function isDoubleWidth(): bool
    {
        return \in_array($this->values['size'], [self::DOUBLE_WIDTH, self::DOUBLE], strict: true);
    }

    public function isBlinking(): bool
    {
        return $this->values['blink'] === self::BLINK;
    }

    public function isMasked(): bool
    {
        return $this->values['mask'] === self::MASK;
    }

    public function isUnderlined(): bool
    {
        return $this->values['underline'] === self::UNDERLINE;
    }

    public function isInverse(): bool
    {
        return $this->values['inverse'] === self::INVERSE;
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
     * Whether the Minitel applies ESC byte in mosaic: not the size (ESC L, M, N or O) nor
     * inverse video (ESC ] or ESC \), which it discards there
     */
    public static function appliesInMosaic(string $byte): bool
    {
        return !\in_array(self::group($byte), ['size', 'inverse'], strict: true);
    }

    /**
     * Whether ESC byte sets double height: ESC M (double height) or ESC O (double size)
     */
    public static function isTallSize(string $byte): bool
    {
        return \in_array($byte, [self::DOUBLE_HEIGHT, self::DOUBLE], strict: true);
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
            'size' => $this->isDoubleWidth() ? self::DOUBLE_WIDTH : self::SIZE_NORMAL,
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
     * Rows taken by a character: double height only applies to text characters, as on the
     * Minitel (which ignores ESC M and ESC O for mosaic)
     */
    public function height(): int
    {
        return $this->isDoubleHeight() && !$this->isMosaic() ? 2 : 1;
    }

    /**
     * Videotex codes setting these attributes where current ones are: the charset (SI or SO),
     * then ESC <byte> for each other attribute that differs from what follows it (SI and SO
     * set the size back to normal, withCharset()); '' if none does
     */
    public function codesFrom(self $current): string
    {
        $codes = '';
        if ($this->values['charset'] !== $current->values['charset']) {
            $codes = $this->values['charset'];
            $current = $current->withCharset($codes);
        }
        foreach (self::DEFAULTS as $group => $_) {
            if ($group === 'charset' || $this->values[$group] === $current->values[$group]) {
                continue;
            }
            $codes .= Videotex::ESC . $this->values[$group];
        }

        return $codes;
    }

    /**
     * Group of the attribute set by ESC <byte>, or null if it is not one
     */
    private static function group(string $byte): ?string
    {
        $code = \ord($byte);
        // The colours (Color::index(), 0 to 7)
        if ($code >= self::TEXT_COLOR && $code <= (self::TEXT_COLOR + 7)) {
            return 'fg';
        }
        if ($code >= self::BACKGROUND_COLOR && $code <= (self::BACKGROUND_COLOR + 7)) {
            return 'bg';
        }

        return match ($byte) {
            self::SIZE_NORMAL, self::DOUBLE_HEIGHT, self::DOUBLE_WIDTH, self::DOUBLE => 'size',
            self::BLINK, self::FIXED => 'blink',
            self::MASK, self::UNMASK => 'mask',
            self::UNDERLINE, self::NO_UNDERLINE => 'underline',
            self::INVERSE, self::NO_INVERSE => 'inverse',
            default => null,
        };
    }
}
