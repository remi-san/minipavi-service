<?php

namespace MiniPaviService\Vdt;

/**
 * Character attributes, as an array group => value: the byte after ESC, or SO / SI for the
 * charset. A positioning (US), a home (RS) or a clear screen (FF) sets them back to DEFAULTS.
 */
final class Attributes
{
    public const DEFAULTS = [
        'charset' => "\x0F",
        'fg' => "\x47", 'bg' => "\x50", 'size' => "\x4C", 'blink' => "\x49",
        'mask' => "\x5F", 'underline' => "\x59", 'inverse' => "\x5C",
    ];

    public const TEXT = "\x0F";
    public const MOSAIC = "\x0E";
    public const BLACK_BACKGROUND = "\x50";
    public const NORMAL_SIZE = "\x4C";

    /**
     * Group of the attribute set by ESC <byte>, or null if it is not one
     */
    public static function group(string $byte): ?string
    {
        $code = ord($byte);
        return match (true) {
            $code >= 0x40 && $code <= 0x47 => 'fg',
            $code >= 0x50 && $code <= 0x57 => 'bg',
            $code >= 0x4C && $code <= 0x4F => 'size',
            $code == 0x48 || $code == 0x49 => 'blink',
            $code == 0x58 || $code == 0x5F => 'mask',
            $code == 0x59 || $code == 0x5A => 'underline',
            $code == 0x5C || $code == 0x5D => 'inverse',
            default => null,
        };
    }

    /**
     * Columns taken by a character: double width only applies to text characters
     */
    public static function width(array $attributes): int
    {
        $doubleWidth = in_array($attributes['size'], ["\x4E", "\x4F"], true);
        return $doubleWidth && $attributes['charset'] === self::TEXT ? 2 : 1;
    }
}
