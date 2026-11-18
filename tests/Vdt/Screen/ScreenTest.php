<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt\Screen;

use MiniPaviService\Page\Display\Color;
use MiniPaviService\Vdt\Vdt;
use MiniPaviService\Vdt\Videotex;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ScreenTest extends TestCase
{
    public function testPreviewShowsTextMosaicAndG2Characters(): void
    {
        // Row 1: text, then "e" with an accent (G2), then mosaic cells: with pixels, empty
        // on black, empty on blue. Row 2: cleared with a blue background from column 3.
        $vdt = "\x1F\x41\x41Hi\x19\x42e\x0E\x21\x20\x1B\x54\x20\x1F\x42\x43\x1B\x54\x18";

        $lines = explode("\n", (new Vdt($vdt))->screen()->preview());

        self::assertCount(26, $lines);
        self::assertSame(' 0|' . str_repeat(' ', times: 40) . '|', $lines[0]);
        // G2 decoded, as line() does
        self::assertSame(' 1|Hié# #' . str_repeat(' ', times: 34) . '|', $lines[1]);
        self::assertSame(' 2|  ' . str_repeat(' ', times: 38) . '|', $lines[2]);
        self::assertSame('', $lines[25]);
    }

    public function testClearScreenEmptiesEveryRowButTheStatusRow(): void
    {
        $screen = (new Vdt("\x1F\x40\x41S\x1F\x45\x45X\x0C"))->screen();

        self::assertSame('S', $screen->cell(row: 0, col: 1)?->bytes);
        self::assertNull($screen->cell(row: 5, col: 5));
    }

    public function testLineGivesTheTextOfARowWithItsAccents(): void
    {
        $screen = Vdt::join(
            Vdt::position(1, row: 3),
            Vdt::text('Dernière page, ça coûte 5£ ½ Œuvre'),
            Vdt::line0('Accès refusé'),
        )->screen();

        $text = 'Dernière page, ça coûte 5£ ½ Œuvre';
        self::assertSame($text . str_repeat(' ', max(0, 40 - mb_strlen($text))), $screen->line(3));
        self::assertStringStartsWith('Accès refusé', ltrim($screen->line(0)));
        self::assertSame(str_repeat(' ', times: 40), $screen->line(4));
        self::assertSame('', $screen->line(99));
    }

    public function testLineShowsMosaicAsInThePreview(): void
    {
        // A mosaic cell with pixels, one empty on black, one empty on blue
        $screen = (new Vdt("\x1F\x41\x41\x0E\x21\x20\x1B\x54\x20"))->screen();

        self::assertSame('# #', substr($screen->line(1), offset: 0, length: 3));
    }

    public function testTextGivesEveryRow(): void
    {
        $text = Vdt::position(1, row: 1)->append(Vdt::text('Hello'))->screen()->text();

        $lines = explode("\n", $text);
        self::assertCount(25, $lines);
        self::assertSame(str_pad('Hello', length: 40), $lines[1]);
    }

    public function testDrawsWhatTheContentPositionsAndRepeats(): void
    {
        // Clear screen, position at row 1 column 2, AB, B three more times, red mosaic,
        // clear to the end of the row, back one column, home
        $screen = (new Vdt("\x0C\x1F\x41\x42AB\x12\x43\x1B\x41\x0E\x18\x08\x1E"))->screen();

        self::assertSame(' ABBBB', rtrim($screen->line(1)));
        self::assertTrue($screen->cell(row: 1, col: 7)?->attributes->isMosaic());
        self::assertSame(Color::Red, $screen->cell(row: 1, col: 40)?->attributes->foreground());
    }

    public function testPositionsCanBeGivenInDigits(): void
    {
        self::assertSame(
            'A',
            (new Vdt("\x1F0712A"))
                ->screen()
                ->cell(row: 7, col: 12)
                ?->text(),
        );
    }

    public function testAG2AccentGoesWithItsLetter(): void
    {
        self::assertStringStartsWith(
            'é!',
            (new Vdt("\x1F\x41\x41\x19\x42e!"))
                ->screen()
                ->line(1),
        );
    }

    public function testProtocolSequencesDrawNothing(): void
    {
        // PRO2 (ESC : x y) and PRO1 (ESC 9 x): none of their bytes is a character
        self::assertStringStartsWith(
            'A ',
            (new Vdt("\x1F\x41\x41\x1B\x3A\x69\x43\x1B\x39\x70A"))
                ->screen()
                ->line(1),
        );
    }

    /**
     * What the Minitel shows (checked in MiniPavi's emulator, #172): on row 1, ESC M (double
     * height) and ESC O (double size) are discarded, the characters drawn at normal size, one
     * column each; ESC N (double width) is kept
     *
     * @return iterable<string, array{string, string, int, string}>
     */
    public static function sizesOnRow1(): iterable
    {
        yield 'double size' => [Videotex::SIZE_DOUBLE, 'DS end', 1, 'DS end'];
        yield 'double height' => [Videotex::SIZE_DOUBLE_HEIGHT, 'DH1', 1, 'DH1'];
        // A character in two cells
        yield 'double width' => [Videotex::SIZE_DOUBLE_WIDTH, 'W1', 2, 'WW11'];
    }

    #[DataProvider('sizesOnRow1')]
    public function testRow1HasNoDoubleHeight(string $size, string $text, int $width, string $shown): void
    {
        $screen = Vdt::join(Vdt::position(1, row: 1), new Vdt($size), Vdt::text($text))->screen();

        self::assertSame($shown, rtrim($screen->line(1)));
        for ($col = 1; $col <= \strlen($shown); $col++) {
            $attributes = $screen->cell(1, $col)->attributes ?? self::fail("Nothing at row 1, column {$col}");
            self::assertFalse($attributes->isDoubleHeight());
            self::assertSame($width, $attributes->width());
        }
    }

    public function testDoubleHeightDiscardedOnRow1IsntKeptForTheNextRows(): void
    {
        // ESC M, a, six line feeds (LF), b: both at normal size
        $screen = Vdt::join(
            Vdt::position(1, row: 1),
            new Vdt(Videotex::SIZE_DOUBLE_HEIGHT . 'a' . str_repeat("\x0A", times: 6) . 'b'),
        )->screen();

        self::assertFalse($screen->cell(1, col: 1)?->attributes->isDoubleHeight());
        self::assertSame('b', $screen->cell(7, col: 2)?->bytes);
        self::assertFalse($screen->cell(7, col: 2)?->attributes->isDoubleHeight());
    }

    public function testRow2HasDoubleHeight(): void
    {
        // Its top half on row 1
        $screen = Vdt::join(
            Vdt::position(30, row: 2),
            new Vdt(Videotex::SIZE_DOUBLE_HEIGHT),
            Vdt::text('T2'),
        )->screen();

        self::assertTrue($screen->cell(2, col: 30)?->attributes->isDoubleHeight());
    }
}
