<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

/**
 * Text (UTF-8) as Videotex: accented and special characters encoded in G2 (SS2, then their
 * code), a few others as the letter closest to them, as MiniPaviCli's toG2() does (CodesTest
 * checks they encode the same), except where MiniPavi's emulator shows toG2() is wrong: Ç,
 * which toG2() sends as ç (#58)
 *
 * @internal
 */
final class G2
{
    // Each character, and what it is sent as: replaced in this order
    private const CHARACTERS = [
        'é' => Videotex::G2 . "\x42e",
        'è' => Videotex::G2 . "\x41e",
        'à' => Videotex::G2 . "\x41a",
        'ç' => Videotex::G2 . "\x4B\x63",
        'ê' => Videotex::G2 . "\x43e",
        'É' => Videotex::G2 . "\x42E",
        'È' => Videotex::G2 . "\x41E",
        'À' => Videotex::G2 . "\x41A",
        'Ç' => Videotex::G2 . "\x4BC",
        'Ê' => Videotex::G2 . "\x43E",
        'β' => Videotex::G2 . "\x7B",
        'ß' => Videotex::G2 . "\x7B",
        'œ' => Videotex::G2 . "\x7A",
        'Œ' => Videotex::G2 . "\x6A",
        'ü' => Videotex::G2 . "\x48\x75",
        'û' => Videotex::G2 . "\x43\x75",
        'ú' => Videotex::G2 . "\x42\x75",
        'ù' => Videotex::G2 . "\x41\x75",
        'ö' => Videotex::G2 . "\x48\x6F",
        'ô' => Videotex::G2 . "\x43\x6F",
        'ó' => Videotex::G2 . "\x42\x6F",
        'ò' => Videotex::G2 . "\x41\x6F",
        'ï' => Videotex::G2 . "\x48\x69",
        'î' => Videotex::G2 . "\x43\x69",
        'í' => Videotex::G2 . "\x42\x69",
        'ì' => Videotex::G2 . "\x41\x69",
        'ë' => Videotex::G2 . "\x48\x65",
        'ä' => Videotex::G2 . "\x48\x61",
        'â' => Videotex::G2 . "\x43\x61",
        'á' => Videotex::G2 . "\x42\x61",
        '£' => Videotex::G2 . "\x23",
        '°' => Videotex::G2 . "\x30",
        '±' => Videotex::G2 . "\x31",
        '←' => Videotex::G2 . "\x2C",
        '↑' => Videotex::G2 . "\x2D",
        '→' => Videotex::G2 . "\x2E",
        '↓' => Videotex::G2 . "\x2F",
        '¼' => Videotex::G2 . "\x3C",
        '½' => Videotex::G2 . "\x3D",
        '¾' => Videotex::G2 . "\x3E",
        'Â' => Videotex::G2 . "\x43A",
        'Î' => 'I',
        'ō' => 'o',
        '’' => "'",
        "\u{A0}" => ' ',
        'ň' => 'n',
        'ć' => 'c',
        'ř' => 'r',
        'ý' => 'y',
        'š' => 's',
        'ą' => 'a',
        'ā' => 'a',
    ];

    /**
     * The text as Videotex: control characters become spaces, and so do the bytes MiniPaviCli
     * takes as undefined (0x81, 0x8D, 0x8F, 0x90, 0x9D) left in a character it doesn't encode.
     * MiniPaviCli replaces those before encoding the characters, so it never encodes the ones
     * holding them (←, ō, ā): G2 encodes them first (#137)
     */
    public static function encode(string $text): string
    {
        $text = preg_replace('/[\x00-\x1F]/', replacement: ' ', subject: $text) ?? $text;
        $text = strtr($text, self::CHARACTERS);

        return preg_replace('/[\x81\x8D\x8F\x90\x9D]/', replacement: ' ', subject: $text) ?? $text;
    }
}
