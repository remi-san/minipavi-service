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
 * Double height, double width and double size: the rows they may be on, how they wrap, and
 * the halves they show
 */
final class DoubleSizeTest extends TestCase
{
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
        $screen = Screen::of(Vdt::join(Vdt::position(new Position(row: 1)), new Vdt($size), Vdt::text($text)));

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
        $screen = Screen::of(Vdt::join(
            Vdt::position(new Position(row: 1)),
            new Vdt(Videotex::SIZE_DOUBLE_HEIGHT . 'a' . str_repeat("\x0A", times: 6) . 'b'),
        ));

        self::assertFalse($screen->cell(1, col: 1)?->attributes->isDoubleHeight());
        self::assertSame('b', $screen->cell(7, col: 2)?->bytes);
        self::assertFalse($screen->cell(7, col: 2)?->attributes->isDoubleHeight());
    }

    public function testRow2HasDoubleHeight(): void
    {
        // Its top half on row 1
        $screen = Screen::of(Vdt::join(
            Vdt::position(new Position(row: 2, col: 30)),
            new Vdt(Videotex::SIZE_DOUBLE_HEIGHT),
            Vdt::text('T2'),
        ));

        self::assertTrue($screen->cell(2, col: 30)?->attributes->isDoubleHeight());
    }

    /**
     * Every size but the normal one, which the Minitel discards on row 0 (MiniPavi's
     * emulator, decoder.js, setSize(); double width checked by the tester on PR #156)
     *
     * @return iterable<string, array{string}>
     */
    public static function doubleSizes(): iterable
    {
        yield 'double height' => [Videotex::SIZE_DOUBLE_HEIGHT];
        yield 'double width' => [Videotex::SIZE_DOUBLE_WIDTH];
        yield 'double size' => [Videotex::SIZE_DOUBLE];
    }

    #[DataProvider('doubleSizes')]
    public function testSizeIsDiscardedOnRow0(string $size): void
    {
        $screen = Screen::of(new Vdt("\x1F\x40\x41{$size}ZY"));

        $cell = $screen->cell(0, col: 1) ?? self::fail('Nothing on row 0');
        self::assertSame('Z', $cell->bytes);
        self::assertFalse($cell->attributes->isDoubleHeight());
        self::assertFalse($cell->attributes->isDoubleWidth());
        // Normal size: one column
        self::assertSame('ZY', substr($screen->line(0), offset: 0, length: 2));
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
        $cell = Screen::of(new Vdt($vdt))->cell(1, $col) ?? self::fail("Nothing at row 1, column {$col}");

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
        $screen = Screen::of(Vdt::join(Vdt::position(new Position($row, $col)), new Vdt($sent)));

        foreach ($drawn as [$atRow, $atCol, $char]) {
            $cell = $screen->cell($atRow, $atCol) ?? self::fail("Nothing at row {$atRow}, column {$atCol}");
            self::assertSame($char, $cell->bytes, "Row {$atRow}, column {$atCol}");
            self::assertTrue($cell->attributes->isDoubleHeight());
        }
    }

    public function testMosaicCharacterWrapsOneRowDownInDoubleHeight(): void
    {
        // Row 10, column 40: ESC M, SO, then two mosaic characters, which are never tall
        $screen = Screen::of(Vdt::join(
            Vdt::position(new Position(row: 10, col: 40)),
            new Vdt(Videotex::SIZE_DOUBLE_HEIGHT . Videotex::MOSAIC_SET . "\x7F\x21"),
        ));

        self::assertSame("\x21", $screen->cell(11, col: 1)?->bytes);
        self::assertNull($screen->cell(12, col: 1));
    }

    public function testTextAfterSoAndSiWrapsOneRowDown(): void
    {
        // Row 10, column 39: ESC M, A, SO, a mosaic character (column 40), which wraps to row
        // 11, SI, then text: SO and SI set the size back to normal, as the Minitel does, so F
        // wraps to row 12 (checked in MiniPavi's emulator, #26)
        $screen = Screen::of(Vdt::join(
            Vdt::position(new Position(row: 10, col: 39)),
            new Vdt(
                Videotex::SIZE_DOUBLE_HEIGHT
                . 'A'
                . Videotex::MOSAIC_SET
                . "\x7F"
                . Videotex::TEXT_SET
                . str_repeat('C', times: 40)
                . 'F',
            ),
        ));

        $c = $screen->cell(11, col: 1) ?? self::fail('Nothing at row 11, column 1');
        self::assertFalse($c->attributes->isDoubleHeight());
        self::assertSame('F', $screen->cell(12, col: 1)?->bytes);
        self::assertNull($screen->cell(13, col: 1));
    }

    public function testMoveRightWrapsOneRowDownInDoubleHeight(): void
    {
        // Row 20, column 39: ESC M, O, HT from column 40 to row 21 (not 22), Q
        $screen = Screen::of(Vdt::join(
            Vdt::position(new Position(row: 20, col: 39)),
            new Vdt(Videotex::SIZE_DOUBLE_HEIGHT . 'O' . Videotex::CURSOR_RIGHT . 'Q'),
        ));

        self::assertSame('Q', $screen->cell(21, col: 1)?->bytes);
        self::assertNull($screen->cell(22, col: 1));
    }

    public function testTallCharacterShowsItsTopHalfOnTheRowAbove(): void
    {
        // Rows 2 and 3 full of x and y, then ESC M and AB from row 3, column 1: their top
        // halves are drawn over row 2's x, as in MiniPavi's emulator (#141)
        $screen = Screen::of(
            new Vdt("\x1F\x42\x41x\x12\x67\x1F\x43\x41y\x12\x67\x1F\x43\x41" . Videotex::SIZE_DOUBLE_HEIGHT . 'AB'),
        );

        self::assertSame('AB' . str_repeat('x', times: 38), $screen->line(2));
        self::assertSame('AB' . str_repeat('y', times: 38), $screen->line(3));
        $top = $screen->cell(2, col: 1) ?? self::fail('Nothing at row 2, column 1');
        self::assertTrue($top->attributes->isDoubleHeight());
        self::assertFalse($top->part->isBottom());
        self::assertTrue($screen->cell(3, col: 1)?->part->isBottom());
    }

    public function testTopHalfIsOnTheZoneOfTheRowAbove(): void
    {
        // Row 3: x, then a zone on blue to the end of the row; ESC M and ABC from row 4,
        // column 5: their top halves are on blue, their bottom halves on black (the emulator)
        $screen = Screen::of(new Vdt(
            "\x1F\x43\x41x"
            . Videotex::backgroundColor(Color::Blue)
            . ' '
            . Videotex::CLEAR_LINE
            . "\x1F\x44\x45"
            . Videotex::SIZE_DOUBLE_HEIGHT
            . 'ABC',
        ));

        self::assertSame('A', $screen->cell(3, col: 5)?->bytes);
        self::assertSame(Color::Blue, $screen->cell(3, col: 5)?->attributes->background());
        self::assertSame(Color::Black, $screen->cell(4, col: 5)?->attributes->background());
    }

    public function testDoubleSizeCharacterShowsAQuarterInEachOfItsCells(): void
    {
        $screen = Screen::of(new Vdt("\x1F\x46\x41" . Videotex::SIZE_DOUBLE . 'W'));

        // Each cell: right, bottom (1 if so)
        $parts = [];
        foreach ([[5, 1], [5, 2], [6, 1], [6, 2]] as [$row, $col]) {
            $cell = $screen->cell($row, $col) ?? self::fail("Nothing at row {$row}, column {$col}");
            self::assertSame('W', $cell->bytes);
            $parts[] = ($cell->part->isRight() ? '1' : '0') . ($cell->part->isBottom() ? '1' : '0');
        }
        self::assertSame(['00', '10', '01', '11'], $parts);
        self::assertNull($screen->cell(5, col: 3));
    }

    public function testDoubleSizeCharacterInTheLastColumnShowsItsLeftQuarters(): void
    {
        // Row 6, column 40: ESC O and W, its top left quarter on row 5, its bottom left one
        // on row 6, as in MiniPavi's emulator (#141); Z wraps two rows down
        $screen = Screen::of(new Vdt("\x1F\x46\x68" . Videotex::SIZE_DOUBLE . 'WZ'));

        $top = $screen->cell(5, col: 40) ?? self::fail('Nothing at row 5, column 40');
        self::assertSame('W', $top->bytes);
        self::assertFalse($top->part->isRight());
        self::assertFalse($top->part->isBottom());
        self::assertTrue($screen->cell(6, col: 40)?->part->isBottom());
        self::assertSame('ZZ', substr($screen->line(8), offset: 0, length: 2));
    }

    /**
     * A tall delimiter shows its top half on the row above, on its zone's background (#151,
     * checked in MiniPavi's emulator by the tester on PR #150), even once a tall character
     * from the row below draws over its bottom half; a double-size one in both of its columns
     * there, as the emulator writes it in each of its cells (decoder.js, printDelimiter())
     *
     * @return iterable<string, array{string, string, array{?Color, ?Color}}> the size and
     *                                                                       the space drawn on
     *                                                                       row 10, column 2,
     *                                                                       what is drawn
     *                                                                       after, and the
     *                                                                       backgrounds of
     *                                                                       row 9, columns 2
     *                                                                       and 3
     */
    public static function tallDelimiters(): iterable
    {
        $blue = Videotex::backgroundColor(Color::Blue) . ' ';
        $tallX = "\x1F\x4B\x42" . Videotex::SIZE_DOUBLE_HEIGHT . 'X';

        yield 'a delimiter' => [Videotex::SIZE_DOUBLE_HEIGHT . $blue, '', [Color::Blue, null]];
        yield 'a space' => [Videotex::SIZE_DOUBLE_HEIGHT . ' ', '', [Color::Black, null]];
        yield 'a delimiter, drawn over from the row below' => [
            Videotex::SIZE_DOUBLE_HEIGHT . $blue,
            $tallX,
            [Color::Blue, null],
        ];
        yield 'a space, drawn over from the row below' => [
            Videotex::SIZE_DOUBLE_HEIGHT . ' ',
            $tallX,
            [Color::Black, null],
        ];
        yield 'a double-size delimiter' => [Videotex::SIZE_DOUBLE . $blue, '', [Color::Blue, Color::Blue]];
        yield 'a double-size space' => [Videotex::SIZE_DOUBLE . ' ', '', [Color::Black, Color::Black]];
    }

    /**
     * @param array{?Color, ?Color} $backgrounds
     */
    #[DataProvider('tallDelimiters')]
    public function testTallDelimiterShowsItsTopHalfOnTheRowAbove(
        string $space,
        string $after,
        array $backgrounds,
    ): void {
        $screen = Screen::of(new Vdt("\x1F\x4A\x42{$space}" . Videotex::SIZE_NORMAL . "   {$after}"));

        self::assertSame($backgrounds, [
            $screen->cell(9, col: 2)?->attributes->background(),
            $screen->cell(9, col: 3)?->attributes->background(),
        ]);
    }

    public function testDoubleWidthCharacterInTheLastColumnStaysInIt(): void
    {
        $screen = Screen::of(Vdt::join(
            Vdt::position(new Position(row: 3, col: 40)),
            new Vdt(Videotex::SIZE_DOUBLE_WIDTH . 'A'),
        ));

        self::assertSame('A', $screen->cell(3, col: 40)?->bytes);
        self::assertSame(str_repeat(' ', times: 39) . 'A', $screen->line(3));
    }
}
