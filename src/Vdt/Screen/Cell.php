<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Vdt\Videotex;

/**
 * Character shown in a cell of the Screen, with its attributes (see Attributes)
 */
final readonly class Cell
{
    // G2 characters (after SS2) without accent, as text
    private const G2_CHARACTERS = [
        "\x23" => '£',
        "\x24" => '$',
        "\x26" => '#',
        "\x27" => '§',
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
        "\x7B" => 'ß',
    ];

    // Letters with an accent of G2 (grave, acute, circumflex, diaeresis, cedilla), as text
    private const G2_ACCENTS = [
        "\x41" => [
            'a' => 'à',
            'e' => 'è',
            'i' => 'ì',
            'o' => 'ò',
            'u' => 'ù',
            'A' => 'À',
            'E' => 'È',
            'I' => 'Ì',
            'O' => 'Ò',
            'U' => 'Ù',
        ],
        "\x42" => [
            'a' => 'á',
            'e' => 'é',
            'i' => 'í',
            'o' => 'ó',
            'u' => 'ú',
            'y' => 'ý',
            'A' => 'Á',
            'E' => 'É',
            'I' => 'Í',
            'O' => 'Ó',
            'U' => 'Ú',
            'Y' => 'Ý',
        ],
        "\x43" => [
            'a' => 'â',
            'e' => 'ê',
            'i' => 'î',
            'o' => 'ô',
            'u' => 'û',
            'A' => 'Â',
            'E' => 'Ê',
            'I' => 'Î',
            'O' => 'Ô',
            'U' => 'Û',
        ],
        "\x48" => [
            'a' => 'ä',
            'e' => 'ë',
            'i' => 'ï',
            'o' => 'ö',
            'u' => 'ü',
            'y' => 'ÿ',
            'A' => 'Ä',
            'E' => 'Ë',
            'I' => 'Ï',
            'O' => 'Ö',
            'U' => 'Ü',
        ],
        "\x4B" => ['c' => 'ç', 'C' => 'Ç'],
    ];

    public function __construct(
        public string $bytes,
        public Attributes $attributes,
    ) {}

    /**
     * This cell as shown in zone (ScreenCells, Zones::of()): on its background, masked if it
     * is, and in text underlined if it is (Zone::shownWith())
     */
    public function inZone(Zone $zone): self
    {
        return new self($this->bytes, $zone->shownWith($this->attributes));
    }

    /**
     * The character as text (UTF-8): text characters as they are, G2 ones decoded (an
     * accented letter with its accent; ? for one unknown), mosaic cells as # when they have
     * pixels or a background color, a space otherwise
     */
    public function text(): string
    {
        if ($this->attributes->isMosaic()) {
            return $this->bytes === ' ' && $this->attributes->background() === Color::Black ? ' ' : '#';
        }
        if ($this->bytes === Videotex::SUB) {
            return '?';
        }
        if ($this->bytes[0] !== Videotex::G2) {
            return $this->bytes;
        }

        $code = $this->bytes[1] ?? '';
        $letter = $this->bytes[2] ?? '';

        return $letter === '' ? self::G2_CHARACTERS[$code] ?? '?' : self::G2_ACCENTS[$code][$letter] ?? $letter;
    }
}
