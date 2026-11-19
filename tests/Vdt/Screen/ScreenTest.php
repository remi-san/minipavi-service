<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt\Screen;

use MiniPaviService\Minitel\Color;
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

    public function testDoubleHeightIsDiscardedOnRow0(): void
    {
        $cell = (new Vdt("\x1F\x40\x41\x1B\x4DZ"))
            ->screen()
            ->cell(0, col: 1) ?? self::fail('Nothing on row 0');

        self::assertSame('Z', $cell->bytes);
        self::assertFalse($cell->attributes->isDoubleHeight());
    }

    /**
     * Double height already set when a cursor move brings the cursor onto row 1: kept, the
     * character drawn tall there, over row 0, with the attributes set since (checked in
     * MiniPavi's emulator, #172)
     *
     * @return iterable<string, array{string, int, bool, Color}>
     */
    public static function sizeBroughtOntoRow1(): iterable
    {
        // Row 24, column 10: ESC M, L, LF to row 1, M
        yield 'a line feed' => ["\x1F\x58\x4A\x1B\x4DL\x0AM", 11, false, Color::White];
        // Row 2, column 20: ESC M, V, VT to row 1, red, W
        yield 'a move up, then a colour' => ["\x1F\x42\x54\x1B\x4DV\x0B\x1B\x41W", 21, false, Color::Red];
        // Row 3, column 30: ESC O, S (two columns), two VT to row 1, T
        yield 'double size' => ["\x1F\x43\x5E\x1B\x4FS\x0B\x0BT", 32, true, Color::White];
    }

    #[DataProvider('sizeBroughtOntoRow1')]
    public function testSizeBroughtOntoRow1IsKept(string $vdt, int $col, bool $doubleWidth, Color $color): void
    {
        $cell = (new Vdt($vdt))
            ->screen()
            ->cell(1, $col) ?? self::fail("Nothing at row 1, column {$col}");

        self::assertTrue($cell->attributes->isDoubleHeight());
        self::assertSame($doubleWidth, $cell->attributes->isDoubleWidth());
        self::assertSame($color, $cell->attributes->foreground());
    }

    /**
     * A character wrapping at the end of a row in double height or double size: the next row
     * of text is 2 rows down, from 1 to ScreenSize::ROWS and around (checked in MiniPavi's
     * emulator, #7)
     *
     * @return iterable<string, array{int, int, string, list<array{int, int, string}>}>
     */
    public static function tallWraps(): iterable
    {
        // A, B, C on row 10; D and E on row 12, the text carrying on along it
        yield 'mid-screen' => [
            10,
            38,
            Videotex::SIZE_DOUBLE_HEIGHT . 'ABCDE',
            [[10, 40, 'C'], [12, 1, 'D'], [12, 2, 'E']],
        ];
        // R and S in two columns each, on row 13
        yield 'double size' => [13, 37, Videotex::SIZE_DOUBLE . 'RST', [[13, 39, 'S'], [15, 1, 'T']]];
        yield 'from the last row' => [24, 38, Videotex::SIZE_DOUBLE_HEIGHT . 'ABCDE', [[2, 1, 'D'], [2, 2, 'E']]];
        // Drawn tall on row 1, over row 0
        yield 'to row 1' => [23, 39, Videotex::SIZE_DOUBLE_HEIGHT . 'abc', [[23, 40, 'b'], [1, 1, 'c']]];
        // Double height set on row 2, brought onto row 1 by a VT
        yield 'from row 1' => [
            2,
            40,
            Videotex::SIZE_DOUBLE_HEIGHT . Videotex::CURSOR_UP . 'JK',
            [[1, 40, 'J'], [3, 1, 'K']],
        ];
    }

    /**
     * @param string $sent after positioning the cursor at row, col
     * @param list<array{int, int, string}> $drawn row, column and character of tall cells
     */
    #[DataProvider('tallWraps')]
    public function testTallCharacterWrapsTwoRowsDown(int $row, int $col, string $sent, array $drawn): void
    {
        $screen = Vdt::join(Vdt::position($col, $row), new Vdt($sent))->screen();

        foreach ($drawn as [$atRow, $atCol, $char]) {
            $cell = $screen->cell($atRow, $atCol) ?? self::fail("Nothing at row {$atRow}, column {$atCol}");
            self::assertSame($char, $cell->bytes, "Row {$atRow}, column {$atCol}");
            self::assertTrue($cell->attributes->isDoubleHeight());
        }
    }

    public function testMosaicCharacterWrapsOneRowDownInDoubleHeight(): void
    {
        // Row 10, column 40: ESC M, SO, then two mosaic characters, which are never tall
        $screen = Vdt::join(
            Vdt::position(40, row: 10),
            new Vdt(Videotex::SIZE_DOUBLE_HEIGHT . Videotex::MOSAIC_SET . "\x7F\x21"),
        )->screen();

        self::assertSame("\x21", $screen->cell(11, col: 1)?->bytes);
        self::assertNull($screen->cell(12, col: 1));
    }

    public function testMoveRightWrapsOneRowDownInDoubleHeight(): void
    {
        // Row 20, column 39: ESC M, O, HT from column 40 to row 21 (not 22), Q
        $screen = Vdt::join(
            Vdt::position(39, row: 20),
            new Vdt(Videotex::SIZE_DOUBLE_HEIGHT . 'O' . Videotex::CURSOR_RIGHT . 'Q'),
        )->screen();

        self::assertSame('Q', $screen->cell(21, col: 1)?->bytes);
        self::assertNull($screen->cell(22, col: 1));
    }
}
