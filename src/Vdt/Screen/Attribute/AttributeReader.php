<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen\Attribute;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Vdt\Videotex;

/**
 * Reads the Attribute ESC and a byte set: the one whose code they are
 */
final class AttributeReader
{
    /**
     * The attribute ESC byte sets, or null if ESC byte is another sequence
     *
     * @param string $byte the byte after ESC
     */
    public static function read(string $byte): ?Attribute
    {
        foreach (self::attributes() as $attribute) {
            if ($attribute->code() === Videotex::ESC . $byte) {
                return $attribute;
            }
        }

        return null;
    }

    /**
     * Every attribute: the text and background colours, the sizes, blinking, masking,
     * underlining and inverse video
     *
     * @return list<Attribute>
     */
    private static function attributes(): array
    {
        $attributes = [
            ...CharacterSize::cases(),
            ...Blinking::cases(),
            ...Masking::cases(),
            ...Underlining::cases(),
            ...Inversion::cases(),
        ];
        foreach (Color::cases() as $color) {
            $attributes[] = new TextColor($color);
            $attributes[] = new BackgroundColor($color);
        }

        return $attributes;
    }
}
