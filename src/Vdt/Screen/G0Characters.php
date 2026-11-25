<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

/**
 * The G0 characters the Minitel draws otherwise than in ASCII, as MiniPavi's emulator draws
 * them (its sprite sheet, font/ef9345-g0.png, #143), as the closest character to what it
 * draws. The others are as in ASCII (e.g. 0x5F, a line across the bottom of the cell, and
 * 0x7C, a line down its middle)
 *
 * @internal
 */
final class G0Characters
{
    // 0x7F, a full block: all six pixels in mosaic too, and how the error character (SUB)
    // is shown (Cell::shownBytes())
    public const FULL_BLOCK = "\x7F";

    // The characters, by their code
    private const CHARACTERS = [
        "\x5E" => '↑',
        "\x60" => '─', // a line across the middle of the cell
        "\x7B" => '▏', // a line down its left edge
        "\x7D" => '▕', // a line down its right edge
        "\x7E" => '▔', // a line across its top
        self::FULL_BLOCK => '█',
    ];

    /**
     * The G0 character as text (UTF-8)
     */
    public static function text(string $byte): string
    {
        return self::CHARACTERS[$byte] ?? $byte;
    }
}
