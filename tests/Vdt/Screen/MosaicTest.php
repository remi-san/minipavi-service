<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt\Screen;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Vdt\Screen\Screen;
use MiniPaviService\Vdt\Vdt;
use MiniPaviService\Vdt\Videotex;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What the screen shows in mosaic: its characters, and the attributes it takes or discards
 */
final class MosaicTest extends TestCase
{
    public function testG0CharactersInMosaicAreMosaic(): void
    {
        self::assertSame('#####!', rtrim(Screen::of(new Vdt("\x1F\x41\x41\x0E\x5E\x60\x7B\x7E\x7F\x0F!"))->line(1)));
    }

    public function testSizeSentInMosaicIsIgnored(): void
    {
        // Row 10, column 40: SO, ESC M, SI, then AB: the Minitel ignores the size in mosaic,
        // so B is at normal size, 1 row down (checked in MiniPavi's emulator)
        $screen = Screen::of(Vdt::join(
            Vdt::position(new Position(row: 10, col: 40)),
            new Vdt(Videotex::MOSAIC_SET . Videotex::SIZE_DOUBLE_HEIGHT . Videotex::TEXT_SET . 'AB'),
        ));

        $b = $screen->cell(11, col: 1) ?? self::fail('Nothing at row 11, column 1');
        self::assertSame('B', $b->bytes);
        self::assertFalse($b->attributes->isDoubleHeight());
        self::assertNull($screen->cell(12, col: 1));
    }

    public function testErrorCharacterIsAMosaicCellWithPixelsInMosaic(): void
    {
        $screen = Screen::of(new Vdt("\x1F\x41\x41\x0E" . Videotex::SUB));

        self::assertSame('#', rtrim($screen->line(1)));
    }

    public function testUnderlinedMosaicIsSeparated(): void
    {
        // SO, ESC Z, red, a mosaic character, then one after SO again (no longer separated);
        // SO first, as it turns separation off
        $screen = Screen::of(
            new Vdt(
                Videotex::MOSAIC_SET
                . Videotex::UNDERLINE_ON
                . Videotex::ESC
                . "\x41\x7F"
                . Videotex::MOSAIC_SET
                . "\x7F",
            ),
        );

        $separated = $screen->cell(1, col: 1) ?? self::fail('Nothing at row 1, column 1');
        self::assertTrue($separated->attributes->isMosaic());
        self::assertTrue($separated->attributes->isSeparated());
        self::assertSame(Color::Red, $separated->attributes->foreground());
        self::assertFalse($screen->cell(1, col: 2)?->attributes->isSeparated());
    }

    public function testUnderlinedTextIsntSeparated(): void
    {
        $a = Screen::of(new Vdt(Videotex::UNDERLINE_ON . ' A'))->cell(1, col: 2) ?? self::fail('Nothing at column 2');

        self::assertTrue($a->attributes->isUnderlined());
        self::assertFalse($a->attributes->isSeparated());
    }

    /**
     * As the Minitel does: in mosaic, ESC L, M, N, O and ESC ] are discarded (MiniPavi's
     * emulator, setSize() and setInvert())
     *
     * @return iterable<string, array{string}>
     */
    public static function discardedInMosaic(): iterable
    {
        yield 'double height' => [Videotex::SIZE_DOUBLE_HEIGHT];
        yield 'double width' => [Videotex::SIZE_DOUBLE_WIDTH];
        yield 'double size' => [Videotex::SIZE_DOUBLE];
        yield 'inverse' => [Videotex::INVERSE_ON];
    }

    #[DataProvider('discardedInMosaic')]
    public function testSizesAndInverseAreDiscardedInMosaic(string $sent): void
    {
        $screen = Screen::of(Vdt::join(
            Vdt::position(new Position(row: 10)),
            new Vdt(Videotex::MOSAIC_SET . $sent . "\x7F"),
        ));

        $cell = $screen->cell(10, col: 1) ?? self::fail('Nothing at row 10, column 1');
        self::assertTrue($cell->attributes->isMosaic());
        self::assertFalse($cell->attributes->isDoubleHeight());
        self::assertFalse($cell->attributes->isDoubleWidth());
        self::assertFalse($cell->attributes->isInverse());
    }

    /**
     * In mosaic, the text colour, blinking, the background colour and separation apply at
     * once, to the next mosaic character
     */
    public function testColorsBlinkingAndSeparationApplyAtOnceInMosaic(): void
    {
        $screen = Screen::of(new Vdt(
            Videotex::MOSAIC_SET
            . Videotex::textColor(Color::Red)
            . Videotex::BLINK_ON
            . Videotex::backgroundColor(Color::Blue)
            . Videotex::UNDERLINE_ON
            . "\x7F",
        ));

        $cell = $screen->cell(1, col: 1) ?? self::fail('Nothing at column 1');
        self::assertSame(Color::Red, $cell->attributes->foreground());
        self::assertTrue($cell->attributes->isBlinking());
        self::assertSame(Color::Blue, $cell->attributes->background());
        self::assertTrue($cell->attributes->isSeparated());
    }
}
