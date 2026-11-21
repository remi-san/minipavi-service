<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt\Screen;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Vdt\Screen\Attribute\AttributeReader;
use MiniPaviService\Vdt\Screen\Attribute\BackgroundColor;
use MiniPaviService\Vdt\Screen\Attribute\Blinking;
use MiniPaviService\Vdt\Screen\Attribute\CharacterSize;
use MiniPaviService\Vdt\Screen\Attribute\Inversion;
use MiniPaviService\Vdt\Screen\Attribute\Masking;
use MiniPaviService\Vdt\Screen\Attribute\TextColor;
use MiniPaviService\Vdt\Screen\Attribute\Underlining;
use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Zone;
use MiniPaviService\Vdt\Videotex;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The attributes Videotex content sets with ESC and a byte, read from it and written back
 */
final class AttributeTest extends TestCase
{
    // The bytes after ESC setting an attribute: the colours, the sizes, blinking, masking,
    // underlining and inverse video
    private const ATTRIBUTE_BYTES =
        "\x40\x41\x42\x43\x44\x45\x46\x47\x48\x49\x4C\x4D\x4E\x4F"
            . "\x50\x51\x52\x53\x54\x55\x56\x57\x58\x59\x5A\x5C\x5D\x5F";

    /**
     * Each byte after ESC sets an attribute or not, as before attributes were typed; the
     * attribute's code is ESC and that byte
     */
    public function testEachAttributeIsReadFromItsByteAndWrittenBackAsIt(): void
    {
        for ($code = 0; $code < 0x80; $code++) {
            $byte = \chr($code);
            $attribute = AttributeReader::read($byte);
            if (!str_contains(self::ATTRIBUTE_BYTES, $byte)) {
                self::assertNull($attribute, bin2hex($byte));
                continue;
            }
            self::assertNotNull($attribute, bin2hex($byte));
            self::assertSame(Videotex::ESC . $byte, $attribute->code());
        }
    }

    /**
     * @return iterable<string, array{Color}>
     */
    public static function colors(): iterable
    {
        foreach (Color::cases() as $color) {
            yield $color->value => [$color];
        }
    }

    #[DataProvider('colors')]
    public function testAColorIsSetByTheCodeOfItsIndex(Color $color): void
    {
        $text = new TextColor($color);
        $background = new BackgroundColor($color);

        self::assertSame(Videotex::ESC . \chr(0x40 + $color->index()), $text->code());
        self::assertSame(Videotex::ESC . \chr(0x50 + $color->index()), $background->code());
        self::assertSame($color, Attributes::defaults()->with($text)->foreground());
        self::assertSame($color, Attributes::defaults()->with($background)->background());
    }

    /**
     * Each attribute sets its own character attribute, and its opposite sets it back
     */
    public function testEachAttributeSetsItsCharacterAttribute(): void
    {
        $defaults = Attributes::defaults();

        self::assertTrue($defaults->with(Blinking::Blink)->isBlinking());
        self::assertTrue($defaults->with(Masking::Mask)->isMasked());
        self::assertTrue($defaults->with(Underlining::Underline)->isUnderlined());
        self::assertTrue($defaults->with(Inversion::Inverse)->isInverse());
        self::assertTrue($defaults->with(CharacterSize::Double)->isDoubleHeight());
        foreach ([
            [Blinking::Blink, Blinking::Fixed],
            [Masking::Mask, Masking::Unmask],
            [Underlining::Underline, Underlining::NoUnderline],
            [Inversion::Inverse, Inversion::NoInverse],
            [CharacterSize::Double, CharacterSize::Normal],
            [new TextColor(Color::Red), new TextColor(Color::White)],
            [new BackgroundColor(Color::Red), new BackgroundColor(Color::Black)],
        ] as [$on, $off]) {
            self::assertEquals($defaults, $defaults->with($on)->with($off), $on->code());
            self::assertNotEquals($defaults, $defaults->with($on), $on->code());
        }
    }

    /**
     * Only masking, underlining and the background colour wait for a delimiter: the others
     * leave the zone attributes as they are
     */
    public function testOnlyZoneAttributesWait(): void
    {
        $zone = Zone::none();

        foreach ([
            new TextColor(Color::Red),
            CharacterSize::DoubleWidth,
            Blinking::Blink,
            Inversion::Inverse,
        ] as $attribute) {
            self::assertFalse($attribute->waitsIn(Attributes::defaults()), $attribute->code());
            self::assertSame($zone, $attribute->waitingIn($zone), $attribute->code());
        }
        foreach ([new BackgroundColor(Color::Red), Masking::Mask, Underlining::Underline] as $attribute) {
            self::assertTrue($attribute->waitsIn(Attributes::defaults()), $attribute->code());
            self::assertTrue($attribute->waitingIn($zone)->isWaiting(), $attribute->code());
        }
    }

    /**
     * A size is double height when tall, double width when wide; in a single row (on rows 0
     * and 1), double height is normal size, and double size double width
     */
    public function testSizes(): void
    {
        $sizes = [];
        foreach (CharacterSize::cases() as $size) {
            $sizes[$size->name] = [$size->isTall(), $size->isWide(), $size->flattened()];
        }

        self::assertSame(
            [
                'Normal' => [false, false, CharacterSize::Normal],
                'DoubleHeight' => [true, false, CharacterSize::Normal],
                'DoubleWidth' => [false, true, CharacterSize::DoubleWidth],
                'Double' => [true, true, CharacterSize::DoubleWidth],
            ],
            $sizes,
        );
    }
}
