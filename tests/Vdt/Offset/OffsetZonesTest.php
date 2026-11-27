<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt\Offset;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Tests\Support\MovedScreens;
use MiniPaviService\Tests\Vdt\Screen\ZonesTest;
use MiniPaviService\Vdt\Offset;
use MiniPaviService\Vdt\Screen\Screen;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;

/**
 * Content moved by Offset keeps its attributes and zones: Offset writes their delimiters
 * again where the move loses them
 */
final class OffsetZonesTest extends TestCase
{
    public function testOffsetMovesTextAndKeepsItsAttributes(): void
    {
        // HELLO on row 1, then four red A (the last three as a repetition) on row 2
        $moved = Screen::of((new Offset(x: 2, y: 3))->apply(
            new Vdt("\x1F\x41\x41HELLO\x1F\x42\x41\x1B\x41AA\x12\x42"),
        ));

        self::assertSame('  HELLO', rtrim($moved->line(4)));
        self::assertSame('  AAAA', rtrim($moved->line(5)));
        // Still red, after the positioning, which resets the attributes
        foreach ([3, 6] as $col) {
            self::assertSame(Color::Red, $moved->cell(row: 5, col: $col)?->attributes->foreground());
        }
        self::assertSame('', trim($moved->line(1) . $moved->line(2)));
    }

    public function testOffsetKeepsTheSizeSetAfterSi(): void
    {
        // Row 10: SO, ESC M (ignored in mosaic), a mosaic character, SI, ESC M, A: SI sets the
        // size back to normal, so offset() must send ESC M after it again (#26)
        $moved = Screen::of((new Offset(x: 0, y: 2))->apply(new Vdt("\x1F\x4A\x41\x0E\x1B\x4D\x7F\x0F\x1B\x4DA")));

        self::assertSame('A', $moved->cell(row: 12, col: 2)?->bytes);
        self::assertTrue($moved->cell(row: 12, col: 2)?->attributes->isDoubleHeight());
    }

    public function testOffsetKeepsInverseAndUnderlineSetAfterSi(): void
    {
        // Row 10: ESC ], ESC Z, a space, A, SO, a mosaic character, SI, ESC ], ESC Z, a space,
        // B: SO and SI turn them off, so offset() must send them after the SI again (#26), and
        // the mosaic cell ends the zone, so the space after it must start it again (#28)
        $moved = Screen::of((new Offset(x: 0, y: 2))->apply(
            new Vdt("\x1F\x4A\x41\x1B\x5D\x1B\x5A A\x0E\x7F\x0F\x1B\x5D\x1B\x5A B"),
        ));

        $b = $moved->cell(row: 12, col: 5) ?? self::fail('Nothing at row 12, column 5');
        self::assertSame('B', $b->bytes);
        self::assertTrue($b->attributes->isInverse());
        self::assertTrue($b->attributes->isUnderlined());
    }

    public function testOffsetWritesDelimitersAgain(): void
    {
        // Row 10: ESC Z, a space, A, B, ESC Y, C, a space (ending the zone), D: moved, each
        // delimiter is sent again with its ESC Z or ESC Y just before it (#28)
        $vdt = new Vdt("\x1F\x4A\x41\x1B\x5A AB\x1B\x59C D");

        self::assertSame(
            "\x1F\x4A\x41\x1B\x5A ABC\x1B\x59 D",
            (new Offset(x: 0, y: 0))
                ->apply($vdt)
                ->toString(),
        );
        $moved = Screen::of((new Offset(x: 2, y: 1))->apply($vdt));
        $underlined = array_map(
            static fn(int $col): bool => $moved->cell(row: 11, col: $col)?->attributes->isUnderlined() === true,
            range(3, end: 8),
        );
        // Columns 3 to 8: the delimiter, A, B, C, the delimiter, D
        self::assertSame([false, true, true, true, false, false], $underlined);
    }

    /**
     * A zone (#28, #30) is kept moved, its delimiter written again, and its background,
     * masking and underline sent only before it, as on the Minitel they wait for one
     */
    #[DataProviderExternal(ZonesTest::class, 'backgroundZones')]
    #[DataProviderExternal(ZonesTest::class, 'maskZones')]
    #[DataProviderExternal(ZonesTest::class, 'underlineZones')]
    #[DataProviderExternal(ZonesTest::class, 'zonesOnTheRowAbove')]
    public function testOffsetKeepsTheZones(string $sent): void
    {
        $original = Vdt::join(Vdt::position(new Position(row: 10)), new Vdt($sent));

        MovedScreens::assertScreenMoved($original, x: 2, y: 1);
    }

    /**
     * @return iterable<string, array{string, int, string}> content (after positioning at row
     *                                                     10, column 1), x, and the underline
     *                                                     of columns 1 to 6 once moved by x
     */
    public static function spacesKeepingTheirZone(): iterable
    {
        // The delimiter goes off the screen: the first space after it starts its zone again,
        // what is before that space can't be (no column left for a delimiter)
        yield 'a delimiter off the screen' => ["\x1B\x5A AB CD", -1, '...UU '];
    }

    #[DataProvider('spacesKeepingTheirZone')]
    public function testOffsetWritesASpaceAsADelimiterWhenItWouldLoseItsZone(
        string $sent,
        int $x,
        string $expected,
    ): void {
        $moved = Screen::of((new Offset($x, y: 0))->apply(Vdt::join(
            Vdt::position(new Position(row: 10)),
            new Vdt($sent),
        )));

        $cells = '';
        for ($col = 1; $col <= 6; $col++) {
            $cells .= match ($moved->cell(10, $col)?->attributes->isUnderlined()) {
                null => ' ',
                true => 'U',
                false => '.',
            };
        }
        self::assertSame($expected, $cells);
    }

    /**
     * Rows 10 moved left, whose zones the columns going off the screen change after the cells
     * on the screen are drawn: those cells show the zone they are in once all is drawn, as in
     * MiniPavi's emulator (#126), not the one they were drawn in
     *
     * @return iterable<string, array{string, int}> what is drawn from row 10, column 1, and x
     */
    public static function zonesChangedOffTheScreen(): iterable
    {
        // ESC R, CAN, its first space a green delimiter, then XY over it: the row is black
        yield 'a clear line, its delimiter drawn over' => ["\x1B\x52\x18XY", -2];
        yield 'a clear line after characters, its delimiter drawn over' => ["AB\x1B\x52\x18XY", -4];
        yield 'a clear line, its delimiter drawn over once positioned' => ["\x1B\x52\x18\x1F\x4A\x41X", -1];
        yield 'spaces, their delimiter drawn over' => ["\x1B\x52    \x1F\x4A\x41X", -1];
        // A, then the delimiter in column 2, the last one going off the screen, drawn over
        yield 'the last column going off the screen' => ["A\x1B\x52    \x1F\x4A\x42X", -2];
        // Spaces and ABC from column 2, then a green delimiter in column 1: they are green
        yield 'a delimiter drawn after the cells it colours' => ["\x1F\x4A\x42   ABC\x1F\x4A\x41\x1B\x52 ", -1];
        // Nothing drawn over: CAN's cells stay green, its delimiter gone off the screen
        yield 'a clear line, its delimiter kept' => ["\x1B\x52\x18", -2];
        // A green delimiter, then a clear screen: the spaces drawn after it are black
        yield 'a delimiter a clear screen erases' => ["\x1B\x52 \x0C\x1F\x4A\x42   ", -1];
        // CAN's delimiter goes off the screen, X then drawn in the column moved to column 1:
        // the first cleared cell X doesn't draw over starts the zone again
        yield 'a clear line, drawn over in the column moved to column 1' => ["\x1B\x52\x18\x1F\x4A\x42X", -1];
        yield 'a clear line after a delimiter, drawn over' => ["\x1B\x52 \x18X", -1];
        // Spaces, the first one a green delimiter, then X over the second one
        yield 'spaces, drawn over in the column moved to column 1' => ["\x1B\x52    \x1F\x4A\x42X", -1];
        // The same, drawn over by the top half of a double-height X, from the row below
        yield 'a clear line, drawn over by a tall character' => [
            "\x1F\x49\x41\x1B\x52\x18\x1F\x4A\x42\x1B\x4DX",
            -1,
        ];
        yield 'spaces, drawn over by a tall character' => ["\x1B\x52     \x1F\x4B\x42\x1B\x4DX", -1];
    }

    /**
     * The zones a column left on the screen changes after the cells after it are drawn: the
     * cells are written as drawn, and the screen changes when that column is (#126)
     *
     * @return iterable<string, array{string, int, string}> what is drawn, x, and how it is
     *                                                      written
     */
    public static function zonesChangedOnTheScreen(): iterable
    {
        // Row 10: spaces from column 3, then a green delimiter in column 2, moved to column 1
        yield 'in the first column left' => [
            "\x1F\x4A\x43   \x1F\x4A\x42\x1B\x52 ",
            -1,
            "\x1F\x4A\x42   \x1F\x4A\x41\x1B\x52 ",
        ];
        // Row 0, the status row, isn't moved: its first column is left on the screen
        yield 'on the status row' => [
            "\x1F\x40\x42   \x1F\x40\x41\x1B\x52 ",
            -1,
            "\x1F\x40\x42   \x1F\x40\x41\x1B\x52 ",
        ];
        // Row 0 with a delimiter in its first column: nothing of it goes off the screen
        yield 'on the status row, from its first column' => [
            "\x1F\x40\x41\x1B\x52    ",
            -1,
            "\x1F\x40\x41\x1B\x52 \x12\x43",
        ];
        // A delimiter drawn over: written as a space, as nothing of its zone lasts
        yield 'a delimiter drawn over' => [
            "\x1F\x40\x41\x1B\x52   \x1F\x40\x41X",
            -1,
            "\x1F\x40\x41   \x1F\x40\x41X",
        ];
        // A clear line's delimiter drawn over by a double-height space, whose bottom half a tall
        // X from row 11 draws over: a clear line starting no zone. The screen would be the
        // same with the delimiter written, as the tall space draws over it: only the bytes
        // show that none is
        yield 'a clear line, its delimiter drawn over by a tall space' => [
            "\x1F\x4A\x41\x1B\x52\x18\x1F\x4A\x41\x1B\x4D \x1F\x4B\x41\x1B\x4DX",
            0,
            "\x1F\x4A\x41\x18\x1B\x4D \x1F\x4B\x41\x1B\x4DX",
        ];
        // A clear line's delimiter drawn over: a clear line starting no zone
        yield 'a clear line, its delimiter drawn over' => [
            "\x1F\x4A\x41\x1B\x52\x18X",
            0,
            "\x1F\x4A\x41\x18X",
        ];
    }

    /**
     * Rows 10 moved left, whose zone went off the screen: it starts again at the first space
     * nothing is drawn over later, up to the clear screen that erases it, if any (#126)
     *
     * @return iterable<string, array{string, int, string}> what is drawn, x, and how it is
     *                                                      written
     */
    public static function cellsDrawnOver(): iterable
    {
        // ESC R, CAN, then X in column 2, moved to column 1: the delimiter in column 2
        yield 'a clear line' => [
            "\x1F\x4A\x41\x1B\x52\x18\x1F\x4A\x42X",
            -1,
            "\x1F\x4A\x41 \x1B\x52 \x12\x65\x1F\x4A\x41X",
        ];
        // ESC R, spaces, then a double-width X over columns 2 and 3: the delimiter in column 4
        yield 'by a double-width character' => [
            "\x1F\x4A\x41\x1B\x52      \x1F\x4A\x42\x1B\x4EX",
            -1,
            "\x1F\x4A\x41  \x1B\x52   \x1F\x4A\x41\x1B\x4EX",
        ];
        // ESC R, a space in column 2, X over it, then a double-width space in columns 3 and 4:
        // the delimiter there
        yield 'next to a double-width space' => [
            "\x1F\x4A\x41\x1B\x52  \x1F\x4A\x42X\x1B\x4E ",
            -1,
            "\x1F\x4A\x41 \x1F\x4A\x41X\x1B\x4E\x1B\x52 ",
        ];
        // ESC R, CAN, then A in columns 2 to 39: the delimiter in column 40, moved to 39
        yield 'up to the last column' => [
            "\x1F\x4A\x41\x1B\x52\x18\x1F\x4A\x42A\x12\x65",
            -1,
            "\x1F\x4A\x41 \x12\x65\x1B\x52 \x1F\x4A\x41A\x12\x65",
        ];
        // ESC R, a space, A in column 2, then a space over it: A written as it is, then the
        // delimiter over it
        yield 'over a character' => ["\x1F\x4A\x41\x1B\x52 A\x1F\x4A\x42 ", -1, "\x1F\x4A\x41A\x1F\x4A\x41\x1B\x52 "];
        // A masked zone, then column 2 empty: the masking runs past it, up to A
        yield 'after an empty cell' => ["\x1F\x4A\x41\x1B\x58 \x1F\x4A\x43 A", -1, "\x1F\x4A\x42\x1B\x58 A"];
        // ESC M ESC R, a green double-height delimiter, then tall spaces: the zone starts again
        // in the first one, whose top half shows it too, as a tall delimiter (the screen
        // would be the same with the space written, then delimiters at normal height over
        // both halves: only the bytes show which is written)
        yield 'in a tall space whose top half shows the zone' => [
            "\x1F\x4A\x41\x1B\x4D\x1B\x52    ",
            -1,
            "\x1F\x4A\x41\x1B\x4D\x1B\x52   ",
        ];
        // CAN's zone is erased by a clear screen, not drawn over: it starts again
        yield 'erased' => ["\x1F\x4A\x41\x1B\x52\x18\x0C", -2, "\x1F\x4A\x41\x1B\x52 \x12\x65\x0C"];
        yield 'after a clear screen' => ["\x0C\x1F\x4A\x41\x1B\x52\x18", -2, "\x0C\x1F\x4A\x41\x1B\x52 \x12\x65"];
        // Before the clear screen, CAN's zone isn't drawn over; after it, it is
        yield 'on either side of a clear screen' => [
            "\x1F\x4A\x41\x1B\x52\x18\x0C\x1F\x4A\x41\x1B\x52\x18XY",
            -2,
            "\x1F\x4A\x41\x1B\x52 \x12\x65\x0C\x1F\x4A\x41 \x12\x65",
        ];
    }

    #[DataProvider('cellsDrawnOver')]
    public function testOffsetStartsAZoneAgainAtASpaceNothingIsDrawnOver(string $drawn, int $x, string $written): void
    {
        self::assertSame(
            $written,
            (new Offset($x, y: 0))
                ->apply(new Vdt($drawn))
                ->toString(),
        );
    }

    #[DataProvider('zonesChangedOnTheScreen')]
    public function testOffsetWritesTheCellsAsDrawnWhenAColumnOnTheScreenChangesTheirZone(
        string $drawn,
        int $x,
        string $written,
    ): void {
        self::assertSame(
            $written,
            (new Offset($x, y: 0))
                ->apply(new Vdt($drawn))
                ->toString(),
        );
    }

    #[DataProvider('zonesChangedOffTheScreen')]
    public function testOffsetShowsTheZonesTheColumnsOffTheScreenFinallyLeave(string $drawn, int $x): void
    {
        MovedScreens::assertScreenMoved(new Vdt("\x1F\x4A\x41{$drawn}"), $x, y: 0);
    }
}
