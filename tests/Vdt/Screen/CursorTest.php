<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt\Screen;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Screen;
use MiniPaviService\Vdt\Vdt;
use MiniPaviService\Vdt\Videotex;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Where the cursor goes: positioning, moves, wrapping, and the status row
 */
final class CursorTest extends TestCase
{
    /**
     * A positioning in digits: US, then 0, 1 or 2 and a digit, the row; the cursor goes to its
     * column 1 (MiniPavi's emulator, locate())
     *
     * @return iterable<string, array{string, int, string}> what is sent after a clear screen,
     *                                                      the row, and the start of its text
     */
    public static function positionsInDigits(): iterable
    {
        yield 'row 5, then text' => ["\x1F05Hello", 5, 'Hello'];
        yield 'row 7, then digits' => ["\x1F0712A", 7, '12A'];
        yield 'row 12' => ["\x1F1200X", 12, '00X'];
        yield 'row 24' => ["\x1F24A", 24, 'A'];
        yield 'the status row' => ["\x1F00S", 0, 'S'];
    }

    #[DataProvider('positionsInDigits')]
    public function testPositionsCanBeGivenInDigits(string $sent, int $row, string $text): void
    {
        $screen = Screen::of(new Vdt("\x0C{$sent}"));

        self::assertSame($text, rtrim($screen->line($row)));
        self::assertSame('', trim($screen->line($row === 1 ? 2 : 1)));
    }

    /**
     * A positioning (US) outside the screen, which the Minitel ignores (MiniPavi's emulator,
     * locate(): neither the cursor nor the attributes change)
     *
     * @return iterable<string, array{string}>
     */
    public static function positionsOutsideTheScreen(): iterable
    {
        yield 'column 0' => ["\x1F\x4A\x40"];
        yield 'column 41' => ["\x1F\x4A\x69"];
        yield 'row 25' => ["\x1F\x59\x41"];
        yield 'row -1' => ["\x1F\x3F\x41"];
        // In digits (positionsInDigits()): 2 and 5, 1 and A (10 + 17), and 3, which isn't
        // one of them, so it is the row of a positioning in bytes (0x33 - 0x40)
        yield 'row 25 in digits' => ["\x1F25"];
        yield 'row 27 in digits' => ["\x1F1A"];
        yield 'a row 3 in digits' => ["\x1F30"];
    }

    #[DataProvider('positionsOutsideTheScreen')]
    public function testPositioningOutsideTheScreenIsIgnored(string $position): void
    {
        // Row 5: red A, B, the positioning, then C
        $screen = Screen::of(new Vdt("\x1F\x45\x41\x1B\x41AB{$position}C"));

        self::assertSame('ABC', rtrim($screen->line(5)));
        self::assertSame(Color::Red, $screen->cell(row: 5, col: 3)?->attributes->foreground());
        self::assertSame(25, \count($screen->cells));
    }

    public function testContentStartsAtRow1Column1WithTheDefaultAttributes(): void
    {
        $cell = Screen::of(new Vdt('A'))->cell(1, col: 1) ?? self::fail('Nothing at row 1, column 1');

        self::assertSame('A', $cell->bytes);
        self::assertEquals(Attributes::defaults(), $cell->attributes);
    }

    /**
     * @return iterable<string, array{string, int, int}> the sequence, and where A is drawn
     *                                                    after it
     */
    public static function positionings(): iterable
    {
        yield 'a positioning' => ["\x1F\x45\x47", 5, 7];
        yield 'a home' => [Videotex::HOME, 1, 1];
        yield 'a clear screen' => [Videotex::CLEAR_SCREEN, 1, 1];
    }

    #[DataProvider('positionings')]
    public function testPositioningSetsTheAttributesBackToTheirDefaults(string $sequence, int $row, int $col): void
    {
        // XY, red, inverse, double width, then the sequence and A
        $screen = Screen::of(
            new Vdt(
                'XY' . Videotex::ESC . "\x41" . Videotex::INVERSE_ON . Videotex::SIZE_DOUBLE_WIDTH . "{$sequence}A",
            ),
        );

        $cell = $screen->cell($row, $col) ?? self::fail("Nothing at row {$row}, column {$col}");
        self::assertSame('A', $cell->bytes);
        self::assertEquals(Attributes::defaults(), $cell->attributes);
    }

    public function testMoveUpFromRow1GoesToTheLastRow(): void
    {
        // As the Minitel does in page mode, the status row skipped (moveCursor())
        $screen = Screen::of(new Vdt("\x1F\x41\x43" . Videotex::CURSOR_UP . 'A'));

        self::assertSame('A', $screen->cell(24, col: 3)?->bytes);
        self::assertSame('', trim($screen->line(0)));
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function movesLeftFromColumn1(): iterable
    {
        yield 'to the row above' => ["\x1F\x43\x41", 2];
        // As moving up, the status row skipped
        yield 'from row 1, to the last row' => ["\x1F\x41\x41", 24];
    }

    #[DataProvider('movesLeftFromColumn1')]
    public function testMoveLeftFromColumn1GoesToTheLastColumnOfTheRowAbove(string $position, int $row): void
    {
        $screen = Screen::of(new Vdt($position . Videotex::CURSOR_LEFT . 'A'));

        self::assertSame('A', $screen->cell($row, col: 40)?->bytes);
        self::assertSame(str_repeat(' ', times: 39) . 'A', $screen->line($row));
    }

    public function testTextOnTheStatusRowStaysInItsLastColumn(): void
    {
        // Row 0, column 39: A, B, then C over B, as the status row doesn't wrap (moveCursor())
        $screen = Screen::of(new Vdt("\x1F\x40\x67ABC"));

        self::assertSame(str_repeat(' ', times: 38) . 'AC', $screen->line(0));
        self::assertSame('', trim($screen->line(1)));
    }

    /**
     * As MiniPavi's emulator does (decoder.js, moveCursor() and locate()): on the status row,
     * VT is ignored and BS stays on it, while LF, and HT from the last column, leave it for
     * where the cursor was when a positioning (US) brought it there
     *
     * @return iterable<string, array{string, int, int, string}> what is sent, and the row,
     *                                                           column and character drawn
     *                                                           last
     */
    public static function movesOnTheStatusRow(): iterable
    {
        // The cases of #98, after a clear screen (the cursor at row 1, column 1)
        yield 'VT, ignored' => ["\x0C\x1F\x40\x4AS\x0BV", 0, 11, 'V'];
        yield 'BS from column 1, to column 40' => ["\x0C\x1F\x40\x41\x08B", 0, 40, 'B'];
        yield 'LF, to the position before row 0' => ["\x0C\x1F\x40\x45L\x0AM", 1, 1, 'M'];
        yield 'HT from column 40, to the position before row 0' => ["\x0C\x1F\x40\x68\x09H", 1, 1, 'H'];
        // Row 10, column 8 after X
        yield 'BS within the row' => ["\x1F\x4A\x47X\x1F\x40\x45\x08B", 0, 4, 'B'];
        yield 'HT within the row, to column 40' => ["\x1F\x4A\x47X\x1F\x40\x67\x09H", 0, 40, 'H'];
        yield 'LF, to row 10' => ["\x1F\x4A\x47X\x1F\x40\x45L\x0AM", 10, 8, 'M'];
        yield 'HT from column 40, to row 10' => ["\x1F\x4A\x47X\x1F\x40\x68\x09H", 10, 8, 'H'];
        yield 'LF after a positioning from the status row, as usual' => [
            "\x1F\x4A\x47X\x1F\x40\x45L\x1F\x43\x43\x0AM",
            4,
            3,
            'M',
        ];
        // Each positioning onto row 0 keeps where the cursor was, even on row 0
        yield 'LF after a positioning on row 0, to the first one' => [
            "\x1F\x4A\x47X\x1F\x40\x45\x1F\x40\x50L\x0AM",
            0,
            5,
            'M',
        ];
        yield 'LF after leaving row 0, as usual' => ["\x1F\x4A\x47X\x1F\x40\x45L\x0A\x0AM", 11, 8, 'M'];
    }

    #[DataProvider('movesOnTheStatusRow')]
    public function testMovesOnTheStatusRowFollowTheMinitel(string $sent, int $row, int $col, string $drawn): void
    {
        $screen = Screen::of(new Vdt($sent));

        self::assertSame($drawn, $screen->cell($row, $col)?->bytes);
        self::assertSame(1, substr_count($screen->text(), $drawn));
    }

    public function testLeavingTheStatusRowGivesBackTheAttributesSetBeforeIt(): void
    {
        // Red and underline waiting at row 10, defaults on row 0, then LF: a space, the
        // delimiter starting the underline, and M in red, underlined
        $screen = Screen::of(
            new Vdt("\x1F\x4A\x41" . Videotex::ESC . "\x41" . Videotex::UNDERLINE_ON . "\x1F\x40\x45L\x0A M"),
        );

        $status = $screen->cell(0, col: 5) ?? self::fail('Nothing at row 0, column 5');
        self::assertEquals(Attributes::defaults(), $status->attributes);
        $m = $screen->cell(10, col: 2) ?? self::fail('Nothing at row 10, column 2');
        self::assertSame('M', $m->bytes);
        self::assertSame(Color::Red, $m->attributes->foreground());
        self::assertTrue($m->attributes->isUnderlined());
    }

    public function testTextWrapsToTheNextRow(): void
    {
        $screen = Screen::of(Vdt::join(Vdt::position(new Position(row: 3, col: 40)), Vdt::text('AB')));

        self::assertSame('A', $screen->cell(3, col: 40)?->bytes);
        self::assertSame('B', $screen->cell(4, col: 1)?->bytes);
    }
}
