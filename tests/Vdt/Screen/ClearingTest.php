<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt\Screen;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Tests\Support\ScreenRows;
use MiniPaviService\Vdt\Screen\Screen;
use MiniPaviService\Vdt\Vdt;
use MiniPaviService\Vdt\Videotex;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What clearing the screen (FF) and a line (CAN) clears and draws
 */
final class ClearingTest extends TestCase
{
    public function testClearScreenEmptiesEveryRowButTheStatusRow(): void
    {
        $screen = Screen::of(new Vdt("\x1F\x40\x41S\x1F\x45\x45X\x1F\x58\x41Y\x0C"));

        self::assertSame('S', $screen->cell(row: 0, col: 1)?->bytes);
        self::assertNull($screen->cell(row: 5, col: 5));
        self::assertNull($screen->cell(row: 24, col: 1));
    }

    public function testClearLineClearsUpToTheLastColumn(): void
    {
        // Row 10: a mosaic character on red in column 40, then from column 1, a blue
        // background and CAN, its first space a delimiter
        $screen = Screen::of(new Vdt("\x1F\x4A\x68\x0E\x1B\x51\x7F\x1F\x4A\x41\x1B\x54\x18"));

        $last = $screen->cell(10, col: 40) ?? self::fail('Nothing at row 10, column 40');
        self::assertSame(' ', $last->bytes);
        self::assertFalse($last->attributes->isMosaic());
        self::assertSame(Color::Blue, $last->attributes->background());
    }

    /**
     * CAN prints a space at the cursor, in its attributes, for each column from the cursor's
     * to the last, as MiniPavi's emulator does (decoder.js, clear("eol"), checked there in
     * #141): in double height, they clear the row above too; in double width, they run on
     * over the next row, two rows down in double size, as text wraps
     *
     * @return iterable<string, array{string, array<int, string>}> what is sent after rows 1 to
     *                                                              24 are filled (with a to x,
     *                                                              by row), and the text
     *                                                              of rows
     */
    public static function clearedLines(): iterable
    {
        $tall = Videotex::SIZE_DOUBLE_HEIGHT;
        $wide = Videotex::SIZE_DOUBLE_WIDTH;
        /** @param non-negative-int $count */
        $spaces = static fn(int $count): string => str_repeat(' ', $count);
        /** @param non-negative-int $count */
        $row = static fn(string $char, int $count = 40): string => str_repeat($char, $count);

        yield 'double height' => [
            "\x1F\x43\x41{$tall}ABC" . Videotex::CLEAR_LINE,
            [
                1 => $row('a'),
                2 => 'ABC' . $spaces(37),
                3 => 'ABC' . $spaces(37),
                4 => $row('d'),
            ],
        ];
        yield 'double size' => [
            "\x1F\x51\x41" . Videotex::SIZE_DOUBLE . 'AB' . Videotex::CLEAR_LINE,
            [
                15 => $row('o'),
                16 => 'AABB' . $spaces(36),
                17 => 'AABB' . $spaces(36),
                18 => $spaces(36) . $row('r', count: 4),
                19 => $spaces(36) . $row('s', count: 4),
                20 => $row('t'),
            ],
        ];
        yield 'double width' => [
            "\x1F\x42\x41{$wide}AB" . Videotex::CLEAR_LINE,
            [
                2 => 'AABB' . $spaces(36),
                3 => $spaces(36) . $row('c', count: 4),
                4 => $row('d'),
            ],
        ];
        // From column 2, the 20th space shows its left half in column 40, then 19 more
        yield 'double width from an even column' => [
            "\x1F\x47\x42{$wide}" . Videotex::CLEAR_LINE,
            [
                7 => 'g' . $spaces(39),
                8 => $spaces(38) . $row('h', count: 2),
            ],
        ];
        yield 'double width in the last column' => [
            "\x1F\x56\x68{$wide}" . Videotex::CLEAR_LINE . 'Z',
            [
                22 => $row('v', count: 39) . 'Z',
                23 => $row('w'),
            ],
        ];
        // Green waiting: the first space a delimiter, then 37 more, up to column 38 of row 3
        yield 'double width, its first space a delimiter' => [
            "\x1F\x42\x43{$wide}\x1B\x52" . Videotex::CLEAR_LINE,
            [
                2 => 'bb' . $spaces(38),
                3 => $spaces(38) . $row('c', count: 2),
            ],
        ];
        // From row 24 to row 1, as text wraps
        yield 'double width on the last row' => [
            "\x1F\x58\x45{$wide}" . Videotex::CLEAR_LINE,
            [
                24 => $row('x', count: 4) . $spaces(36),
                1 => $spaces(36) . $row('a', count: 4),
                2 => $row('b'),
            ],
        ];
    }

    /**
     * @param array<int, string> $lines
     */
    #[DataProvider('clearedLines')]
    public function testClearLinePrintsASpaceForEachColumnLeft(string $sent, array $lines): void
    {
        $filled = '';
        for ($row = 1; $row <= 24; $row++) {
            $filled .= "\x1F" . chr(0x40 + $row) . "\x41" . chr(0x60 + $row) . Videotex::REPEAT . chr(0x40 + 39);
        }

        $screen = Screen::of(new Vdt($filled . $sent));

        foreach ($lines as $row => $line) {
            self::assertSame($line, $screen->line($row), "Row {$row}");
        }
    }

    public function testClearLineOnTheStatusRowIsAtNormalSize(): void
    {
        // Row 0, column 30: ESC N, discarded there, underline waiting, CAN: a delimiter in
        // column 30, then spaces a column wide in the underlined zone, as in MiniPavi's
        // emulator (checked by the tester on PR #156)
        $screen = Screen::of(
            new Vdt("\x1F\x40\x5E" . Videotex::SIZE_DOUBLE_WIDTH . Videotex::UNDERLINE_ON . Videotex::CLEAR_LINE),
        );

        self::assertFalse($screen->cell(0, col: 30)?->attributes->isUnderlined());
        self::assertTrue($screen->cell(0, col: 31)?->attributes->isUnderlined());
        self::assertTrue($screen->cell(0, col: 40)?->attributes->isUnderlined());
        self::assertSame(1, $screen->cell(0, col: 31)?->attributes->width());
    }

    public function testTallClearLineStartsItsZoneOnTheRowAboveToo(): void
    {
        // Row 8: x, then a zone on blue to the end of the row; from row 9, column 5: ESC M, a
        // red background waiting, ABC and CAN, its first space a delimiter: on row 8 too, the
        // rest of the row is red (checked in MiniPavi's emulator, #141)
        $screen = Screen::of(new Vdt(
            "\x1F\x48\x41x"
            . Videotex::backgroundColor(Color::Blue)
            . ' '
            . Videotex::CLEAR_LINE
            . "\x1F\x49\x45"
            . Videotex::SIZE_DOUBLE_HEIGHT
            . Videotex::backgroundColor(Color::Red)
            . 'ABC'
            . Videotex::CLEAR_LINE,
        ));

        self::assertSame('.BBBBBBRRRRR', ScreenRows::zones($screen, row: 8, length: 12));
        self::assertSame('    ...RRRRR', ScreenRows::zones($screen, row: 9, length: 12));
        self::assertSame(Color::Red, $screen->cell(8, col: 40)?->attributes->background());
    }

    public function testClearScreenEndsTheZonesOfEveryRowButTheStatusRow(): void
    {
        // An underlined zone from column 1 on rows 0, 1 and 24, a clear screen, then C and D
        // drawn in columns 3 and 4 of rows 1 and 24, and E after the zone of row 0
        $zone = Videotex::UNDERLINE_ON . ' AB';
        $screen = Screen::of(
            new Vdt(
                "\x1F\x40\x41{$zone}\x1F\x41\x41{$zone}\x1F\x58\x41{$zone}\x0C"
                . "\x1F\x41\x43CD\x1F\x58\x43CD\x1F\x40\x44E",
            ),
        );

        self::assertTrue($screen->cell(0, col: 4)?->attributes->isUnderlined());
        foreach ([1, 24] as $row) {
            foreach ([3, 4] as $col) {
                self::assertFalse($screen->cell($row, $col)?->attributes->isUnderlined(), "row {$row}, column {$col}");
            }
        }
    }
}
