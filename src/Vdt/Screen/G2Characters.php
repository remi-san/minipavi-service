<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

/**
 * The G2 characters (after SS2, or SYN, #139) the Minitel has, as MiniPavi's emulator shows them (#90): a
 * character of its own, or an accent (grave, acute, circumflex, diaeresis, cedilla) with the
 * byte after it, whatever it is. Any other is shown as _ (the Minitel's character 0x5F)
 *
 * @internal
 */
final class G2Characters
{
    // What the Minitel shows for a G2 character it doesn't have
    private const UNKNOWN = '_';

    // Characters of their own, by their code
    private const CHARACTERS = [
        "\x23" => '£',
        "\x24" => '$',
        "\x26" => '#',
        "\x2C" => '←',
        "\x2D" => '↑',
        "\x2E" => '→',
        "\x2F" => '↓',
        "\x30" => '°',
        "\x31" => '±',
        "\x38" => '÷',
        "\x3C" => '¼',
        "\x3D" => '½',
        "\x3E" => '¾',
        "\x6A" => 'Œ',
        "\x7A" => 'œ',
    ];

    // The letters each accent goes with, by the accent's code
    private const ACCENTS = [
        "\x41" => ['A' => 'À', 'a' => 'à', 'E' => 'È', 'e' => 'è', 'u' => 'ù'],
        "\x42" => ['E' => 'É', 'e' => 'é'],
        "\x43" => ['A' => 'Â', 'a' => 'â', 'E' => 'Ê', 'e' => 'ê', 'i' => 'î', 'o' => 'ô', 'u' => 'û'],
        "\x48" => ['E' => 'Ë', 'e' => 'ë', 'i' => 'ï'],
        "\x4B" => ['C' => 'Ç', 'c' => 'ç'],
    ];

    /**
     * Whether code (the byte after SS2) is an accent, which takes the byte after it
     */
    public static function isAccent(string $code): bool
    {
        return \array_key_exists($code, self::ACCENTS);
    }

    /**
     * The G2 character as text (UTF-8): bytes are SS2 (or SYN), its code, and, after an accent, the
     * byte it goes with
     */
    public static function text(string $bytes): string
    {
        $code = $bytes[1] ?? '';
        $letter = substr($bytes, offset: 2);

        return $letter === ''
            ? self::CHARACTERS[$code] ?? self::UNKNOWN
            : self::ACCENTS[$code][$letter] ?? self::UNKNOWN;
    }
}
