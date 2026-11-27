<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt;

use MiniPaviService\InvalidValueException;
use MiniPaviService\Minitel\Color;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\ScreenSize;
use MiniPaviService\Tests\Vdt\Screen\CursorTest;
use MiniPaviService\Tests\Vdt\Screen\SequencesTest;
use MiniPaviService\Tests\Vdt\Screen\ZonesTest;
use MiniPaviService\Vdt\Offset;
use MiniPaviService\Vdt\Screen\Attribute\CharacterSize;
use MiniPaviService\Vdt\Screen\Attribute\Masking;
use MiniPaviService\Vdt\Screen\Attribute\Underlining;
use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Cell;
use MiniPaviService\Vdt\Screen\Part;
use MiniPaviService\Vdt\Screen\Screen;
use MiniPaviService\Vdt\Screen\Zone;
use MiniPaviService\Vdt\Vdt;
use MiniPaviService\Vdt\Videotex;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;

final class VdtTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../fixtures/vdt';

    // PHPUnit's assertions take no named arguments: their messages are constants
    private const GOLDEN_MISSING = 'Run the tests with UPDATE_GOLDEN=1 to write it';

    public function testTextShowsItsAccents(): void
    {
        self::assertStringStartsWith('Déjà vu ', Screen::of(Vdt::text('Déjà vu'))->line(1));
        // Control characters can't slip in as text: a clear screen would erase the "a"
        self::assertStringStartsWith('a b ', Screen::of(Vdt::text("a\x0Cb"))->line(1));
    }

    /**
     * Uppercase letters keep their case (#58): Ç is the Minitel's Ç, not ç. The Minitel has
     * no Î, which it would show as _ (as MiniPavi's emulator does): Î is shown as I
     */
    public function testTextShowsUppercaseLettersAsTheMinitelHasThem(): void
    {
        self::assertStringStartsWith('ÇA ç ILE île ', Screen::of(Vdt::text('ÇA ç ÎLE île'))->line(1));
        self::assertStringStartsWith('ÇA ç ILE île ', Screen::of(Vdt::line0('ÇA ç ÎLE île'))->line(0));
    }

    /**
     * The Minitel has no §, which MiniPaviCli::send() sends as ? (#137): the screen shows the ?
     * the Minitel shows, not §
     */
    public function testTextShowsACharacterTheMinitelDoesntHaveAsTheMinitelDoes(): void
    {
        self::assertStringStartsWith('? 1 ', Screen::of(Vdt::text('§ 1'))->line(1));
    }

    public function testJoinsContents(): void
    {
        $a = new Vdt("\x0C");

        self::assertSame("\x0Cab", Vdt::join($a, Vdt::text('a'), Vdt::text('b'))->toString());
        self::assertSame("\x0Cab", $a->append(Vdt::text('a'), Vdt::text('b'))->toString());
        // Immutable
        self::assertSame("\x0C", $a->toString());
    }

    public function testEmptyContentIsNothing(): void
    {
        self::assertTrue((new Vdt())->isEmpty());
        self::assertTrue(Vdt::join()->isEmpty());
        self::assertFalse(Vdt::text(' ')->isEmpty());
    }

    public function testGivesBackItsBytesAsTheyAre(): void
    {
        $vdt = (string) file_get_contents(self::FIXTURES . '/background.vdt');

        self::assertSame($vdt, (new Vdt($vdt))->toString());
        // Equal by its bytes, drawn or not
        $drawn = new Vdt("\x1F\x41\x41Hi");
        Screen::of($drawn);
        self::assertEquals(new Vdt("\x1F\x41\x41Hi"), $drawn);
    }

    /**
     * A REP counts 63 more at most (0x7F): beyond, the count is no longer a 7-bit byte, which
     * the gateway's client (MiniPaviCli::send()) turns into "?", so the character would be shown
     * once. More repetitions follow.
     */
    public function testRepeatsACharacterAsManyTimesAsAsked(): void
    {
        foreach ([1, 40, 64, 65, 100, 128, 129] as $count) {
            $vdt = Vdt::repeat('-', $count);

            self::assertSame(0, preg_match('/[\x80-\xFF]/', $vdt->toString()), "{$count} times: 7-bit");
            self::assertSame(
                str_repeat('-', $count),
                str_replace(["\n", ' '], replace: '', subject: Screen::of($vdt)->text()),
                "{$count} times",
            );
        }
        // The character, 63 more, then 1 more
        self::assertSame("-\x12\x7F\x12\x41", Vdt::repeat('-', count: 65)->toString());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function countsBelowOne(): iterable
    {
        yield 'none' => [0];
        yield 'negative' => [-1];
    }

    #[DataProvider('countsBelowOne')]
    public function testRefusesToRepeatACharacterLessThanOnce(int $count): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage("A character is repeated once at least, got {$count}");

        Vdt::repeat('-', $count);
    }

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

    public function testOffsetMovesATallCharacterWrappedTwoRowsDown(): void
    {
        // Row 10, column 39: ESC M, F, G, then H wrapped to row 12 (#7), moved 2 rows down
        $moved = Screen::of((new Offset(x: 0, y: 2))->apply(new Vdt("\x1F\x4A\x67\x1B\x4DFGH")));

        self::assertSame('G', $moved->cell(row: 12, col: 40)?->bytes);
        self::assertSame('H', $moved->cell(row: 14, col: 1)?->bytes);
        self::assertTrue($moved->cell(row: 14, col: 1)?->attributes->isDoubleHeight());
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

        self::assertScreenMoved($original, x: 2, y: 1);
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

    public function testOffsetWritesTheDelimiterOfAClearLine(): void
    {
        // Row 10: A, ESC Z, CAN (its first space a delimiter), HT, B, C (#28)
        $vdt = new Vdt("\x1F\x4A\x41A\x1B\x5A\x18\x09BC");

        foreach ([[0, 0], [2, 1], [-1, 0]] as [$x, $y]) {
            $moved = Screen::of((new Offset($x, $y))->apply($vdt));
            $underlined = array_map(
                static fn(int $col): bool => $moved->cell(10 + $y, $col + $x)?->attributes->isUnderlined() === true,
                range(max(1, 1 - $x), end: 5),
            );
            // Columns 1 to 5: A, the delimiter, B, C, a cleared space
            self::assertSame(
                \array_slice([false, false, true, true, true], max(0, -$x)),
                $underlined,
                "offset {$x}, {$y}",
            );
        }
    }

    /**
     * @return iterable<string, array{string, int, int}> content, x and y
     */
    public static function clearLinesMoved(): iterable
    {
        // Row 2: ABC, then from column 2
        yield 'onto row 1' => ["\x1F\x42\x41ABC\x1F\x42\x42\x18", 0, -1];
        // Row 10, column 38
        yield 'onto the last column' => ["\x1F\x4A\x66\x18", 2, 0];
        // Row 10, column 1: the cleared cells stop before the column that came from beyond
        yield 'left, from column 1' => ["\x1F\x4A\x41\x18", -1, 0];
        // Row 10: A, ESC Z, CAN, its first space a delimiter, in column 1 once moved
        yield 'left, with its delimiter' => ["\x1F\x4A\x41A\x1B\x5A\x18", -1, 0];
        // Row 10: SO, a red background, applied at once in mosaic, CAN
        yield 'in mosaic, on its background' => ["\x1F\x4A\x41\x0E\x1B\x51\x18", 2, 1];
    }

    #[DataProvider('clearLinesMoved')]
    public function testOffsetMovesAClearLine(string $sent, int $x, int $y): void
    {
        self::assertScreenMoved(new Vdt($sent), $x, $y);
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
        self::assertScreenMoved(new Vdt("\x1F\x4A\x41{$drawn}"), $x, y: 0);
    }

    /**
     * Rows 10 with tall cells, moved left or kept in place: the row above, 9, shows what their
     * top halves show, as in MiniPavi's emulator (#126, #151)
     *
     * @return iterable<string, array{string, int}> what is drawn, and x
     */
    public static function zonesOfTallCells(): iterable
    {
        // A double-height blue delimiter on row 10, drawn over there by a tall X from row 11:
        // its top half, on row 9, still shows it
        yield 'a tall delimiter drawn over on its row only' => [
            "\x1F\x4A\x42\x1B\x4D\x1B\x54 \x1B\x4C   \x1F\x4B\x42\x1B\x4DX",
            0,
        ];
        // The same with ESC R before it: the green zone starts again in column 3
        yield 'after a tall delimiter drawn over on its row only' => [
            "\x1F\x4A\x41\x1B\x52 \x1B\x4D\x1B\x54 \x1B\x4C   \x1F\x4B\x42\x1B\x4DX",
            -1,
        ];
        // ESC M ESC R, a green double-height delimiter, then tall spaces: their zone starts
        // again in column 2, on rows 9 and 10
        yield 'in a tall cell' => ["\x1F\x4A\x41\x1B\x4D\x1B\x52    ", -1];
        // ESC R, then a double-height blue delimiter in column 2 and tall spaces, a tall X from
        // row 11 drawn over column 2 on row 10: row 9 shows the blue zone, row 10 the green one
        // from column 3 (Offset writes the green delimiter at normal height, over the bottom
        // half of a tall space, leaving its blue top half on row 9)
        yield 'over the bottom half of a tall cell whose top half shows another zone' => [
            "\x1F\x4A\x41\x1B\x52 \x1B\x4D\x1B\x54    \x1F\x4B\x42\x1B\x4DX",
            -1,
        ];
        // ESC Q, a red delimiter, then a tall B and a tall space, whose top halves, on row 9,
        // are in no zone
        yield 'over the bottom half of a tall cell whose top half shows no zone' => [
            "\x1F\x4A\x41\x1B\x51 \x1B\x4DB \x1B\x52AA",
            -1,
        ];
        // The same in double size
        yield 'over the bottom half of a double-size cell' => ["\x1F\x4A\x41\x1B\x51 \x1B\x4FB \x1B\x52AA", -1];
        // Row 9 green (ESC R, CAN), then row 10 as above: row 9's zone goes off the screen, and
        // starts again in the top half of the tall space from row 10 (#153)
        yield 'in the top half of a tall space from the row below' => [
            "\x1F\x49\x41\x1B\x52\x18\x1F\x4A\x41\x1B\x51 \x1B\x4DB \x1B\x52AA",
            -1,
        ];
        // The same, row 9 underlined (ESC Z): the top half shows no underline, nor does the
        // delimiter written over it, as the zone before it isn't underlined
        yield 'in the top half of a tall space from the row below, underlined' => [
            "\x1F\x49\x41\x1B\x5A\x1B\x52\x18\x1F\x4A\x41\x1B\x51 \x1B\x4DB \x1B\x52AA",
            -1,
        ];
        // Row 10 losing no zone: only row 9's starts again
        yield 'in the top half of a tall space, its own row keeping its zone' => [
            "\x1F\x49\x41\x1B\x52\x18\x1F\x4A\x42\x1B\x4DB AA",
            -1,
        ];
        // Then spaces drawn after it on row 9, and on row 8 next to the column above it: each
        // in the zone its row shows there
        yield 'in the top half of a tall space, spaces drawn after it' => [
            "\x1F\x49\x41\x1B\x52\x18\x1F\x4A\x41\x1B\x51 \x1B\x4DB \x1B\x52AA\x1F\x49\x48 \x1F\x48\x44 ",
            -1,
        ];
        // In double size: a double-width delimiter over both columns of the top half
        yield 'in the top half of a double-size space from the row below' => [
            "\x1F\x49\x41\x1B\x52\x18\x1F\x4A\x41\x1B\x51 \x1B\x4FB \x1B\x52AA",
            -1,
        ];
        // The same, moved so that only the right half of the space stays: a delimiter one
        // column wide in column 1
        yield 'in the right half of the top half of a double-size space' => [
            "\x1F\x49\x41\x1B\x52\x18\x1F\x4A\x41\x1B\x51 \x1B\x4FB \x1B\x52AA",
            -4,
        ];
        // ESC M ESC Z, an underlining double-height delimiter, then tall spaces, an L at
        // normal height drawn over column 2 on row 10: the zone starts again in column 3, as
        // a tall delimiter, whose top half isn't underlined. Nor does the source's show it:
        // the Minitel underlines a tall cell's bottom half only (#168)
        yield 'in a tall space whose top half is underlined' => [
            "\x1F\x4A\x41\x1B\x4D\x1B\x5A   \x1F\x4A\x42L",
            -1,
        ];
        // The same, as the fuzzer found it, with a double-width L
        yield 'in a tall space whose top half is underlined, after a double-width character' => [
            "\x1F\x4A\x41N\x1B\x4D \x1B\x5AS      \x1F\x4A\x45\x1B\x4EL",
            -5,
        ];
    }

    #[DataProvider('zonesOfTallCells')]
    public function testOffsetShowsTheZonesOfTallCellsOnTheRowAbove(string $drawn, int $x): void
    {
        self::assertScreenMoved(new Vdt($drawn), $x, y: 0);
    }

    /**
     * A tall character's top half shows no underline (MiniPavi's emulator, font-sprite.js,
     * writeChar(): it is drawn at the bottom of the character): two screens differing only
     * by it show the same, for the screen tests (asShown(), #168)
     */
    public function testScreensDifferingByATallCharactersTopHalfUnderlineShowTheSame(): void
    {
        $underlined = self::tallA(top: Underlining::Underline, bottom: Underlining::Underline);
        $topNotUnderlined = self::tallA(top: Underlining::NoUnderline, bottom: Underlining::Underline);

        self::assertShowsMoved($underlined, $topNotUnderlined, x: 0, y: 0);
    }

    /**
     * A tall character's bottom half shows its underline: two screens differing by it don't
     * show the same, for the screen tests (asShown())
     */
    public function testScreensDifferingByATallCharactersBottomHalfUnderlineShowOtherwise(): void
    {
        $underlined = self::tallA(top: Underlining::Underline, bottom: Underlining::Underline);
        $bottomNotUnderlined = self::tallA(top: Underlining::Underline, bottom: Underlining::NoUnderline);

        $this->expectException(AssertionFailedError::class);
        self::assertShowsMoved($underlined, $bottomNotUnderlined, x: 0, y: 0);
    }

    // A screen with a double-height A in column 1 of row 10, its top half on row 9, each half
    // underlined or not
    private static function tallA(Underlining $top, Underlining $bottom): Screen
    {
        $tall = Attributes::defaults()->withSize(CharacterSize::DoubleHeight);

        return new Screen([
            9 => [1 => new Cell('A', $tall->withUnderlining($top), new Part(right: false, bottom: false))],
            10 => [1 => new Cell('A', $tall->withUnderlining($bottom), new Part(right: false, bottom: true))],
        ]);
    }

    /**
     * Double-width and double-size cells moved right partly off the screen: their left half
     * shows in the last column, as the Minitel shows a wide character written there
     * (MiniPavi's emulator, decoder.js, printG0Char(), printDelimiter(): the cells past the
     * last column aren't drawn), not nothing (#152)
     *
     * @return iterable<string, array{string, int}> what is drawn, and x
     */
    public static function wideCellsMovedOffTheRight(): iterable
    {
        // Row 10, column 36: X and Y in double width, Y's left half in column 40
        yield 'double width, by 2' => ["\x1F\x4A\x64\x1BNXY", 2];
        // X in columns 39 and 40, Y off the screen
        yield 'double width, by 3' => ["\x1F\x4A\x64\x1BNXY", 3];
        // Row 10, column 37: X in double size, its left half in column 40 of rows 9 and 10
        yield 'double size' => ["\x1F\x4A\x65\x1BOX", 3];
        // Row 10, column 39: a red double-width delimiter, then X, moved to column 40
        yield 'a double-width delimiter' => ["\x1F\x4A\x67\x1BN\x1BQ X", 1];
        // The same in double size
        yield 'a double-size delimiter' => ["\x1F\x4A\x67\x1BO\x1BQ X", 1];
    }

    #[DataProvider('wideCellsMovedOffTheRight')]
    public function testOffsetShowsTheLeftHalfOfAWideCellMovedIntoTheLastColumn(string $drawn, int $x): void
    {
        self::assertScreenMoved(new Vdt($drawn), $x, y: 0);
    }

    /**
     * Double-width and double-size spaces and delimiters moved left partly off the screen:
     * the right half that stays shows as a space one column wide does, so it is written as
     * one (at normal width, or double height for double size), and a delimiter's zone starts
     * there (#152). The Minitel can't show a right half alone (MiniPavi's emulator,
     * decoder.js, locate() ignores column 0, and a character is drawn from its left half)
     *
     * @return iterable<string, array{string, int}> what is drawn from row 10, column 1, and x
     */
    public static function wideSpacesMovedOffTheLeft(): iterable
    {
        yield 'a double-width space' => ["\x1F\x4A\x41\x1BN  X", -1];
        // A red double-width delimiter in columns 1 and 2, then X: red from column 1
        yield 'a double-width delimiter' => ["\x1F\x4A\x41\x1BN\x1BQ XY", -1];
        // The same in double size: red from column 1 on rows 9 and 10
        yield 'a double-size delimiter' => ["\x1F\x4A\x41\x1BO\x1BQ XY", -1];
        // A red delimiter in column 1, then a double-width space in columns 2 and 3 and X:
        // the red zone starts again in column 1, the space's right half
        yield 'a double-width space restarting a zone' => ["\x1F\x4A\x41\x1BQ \x1BN XY", -2];
        // The same in double size
        yield 'a double-size space restarting a zone' => ["\x1F\x4A\x41\x1BQ \x1BO XY", -2];
        // Inverted: the right half shows the foreground colour, as a normal-width space does
        yield 'an inverted double-width space' => ["\x1F\x4A\x41\x1B\x5D\x1BN XY", -1];
    }

    /**
     * Double-width and double-size characters other than a space moved left so that only their
     * right half stays: the Minitel can't show a right half alone (a character is drawn from
     * its left half, and a positioning in column 0 is ignored: MiniPavi's emulator, decoder.js,
     * locate()), so it is written as a space one column wide, with its attributes, which shows
     * its background (or its colour, inverted) without its glyph, rather than nothing, which
     * would leave what was drawn there before it (#152)
     *
     * @return iterable<string, array{string, int}> what is drawn from row 10, column 1, and x
     */
    public static function wideCharactersMovedOffTheLeft(): iterable
    {
        // X and Y from column 1: column 1 blank, Y in columns 2 and 3
        yield 'from column 1' => ["\x1F\x4A\x41\x1BNXY", -1];
        // AB, then X over columns 1 and 2, then C: column 1 blank, not B
        yield 'drawn over a character' => ["\x1F\x4A\x41AB\x1F\x4A\x41\x1BNXC", -1];
        // Inverted: column 1 shows the foreground colour
        yield 'inverted' => ["\x1F\x4A\x41AB\x1F\x4A\x41\x1B\x5D\x1BNXC", -1];
        // Double size, over AB on rows 9 and 10: column 1 blank on both
        yield 'double size' => ["\x1F\x49\x41AB\x1F\x4A\x41AB\x1F\x4A\x41\x1BOXC", -1];
    }

    #[DataProvider('wideCharactersMovedOffTheLeft')]
    public function testOffsetWritesTheRightHalfOfAWideCharacterAsASpace(string $drawn, int $x): void
    {
        self::assertScreenMoved(new Vdt($drawn), $x, y: 0);
    }

    /**
     * A zone whose delimiter goes off the screen starts again at the right half of a
     * double-width or double-size character that stays in column 1, written as a space one
     * column wide (#170): the cells after it show their zone from column 2, rather than from
     * the next space
     *
     * @return iterable<string, array{string, int, int}> what is drawn, x and y
     */
    public static function zonesRestartedAtAWideCharactersRightHalf(): iterable
    {
        // Row 10: a red delimiter, AB, two spaces, then a double-width L over columns 3 and 4,
        // and three double-width spaces: red from column 1 once moved
        yield 'a background' => ["\x1F\x4A\x41\x1B\x51 AB  \x1F\x4A\x43\x1B\x4EL   ", -3, 0];
        // The same, underlined: the double-width spaces underlined from column 2
        yield 'an underline' => ["\x1F\x4A\x41\x1B\x5A AB  \x1F\x4A\x43\x1B\x4EL   ", -3, 0];
        // The same, red and underlined: red from column 1, underlined from column 2
        yield 'a background and an underline' => ["\x1F\x4A\x41\x1B\x51\x1B\x5A AB  \x1F\x4A\x43\x1B\x4EL   ", -3, 0];
        // The same, masked (ESC X): masked from column 2
        yield 'a masking' => ["\x1F\x4A\x41\x1B\x58 AB  \x1F\x4A\x43\x1B\x4EL   ", -3, 0];
        // The same, the L inverted
        yield 'an inverted character' => ["\x1F\x4A\x41\x1B\x5A AB  \x1F\x4A\x43\x1B\x5D\x1B\x4EL   ", -3, 0];
        // The same, a double-size L, row 9 red: the zones start again on rows 9 and 10
        yield 'a double-size character' => [
            "\x1F\x49\x41\x1B\x51\x18\x1F\x4A\x41\x1B\x5A AB  \x1F\x4A\x43\x1B\x4FL\x1B\x4E   ",
            -3,
            0,
        ];
        // The fuzzer's (#168): an underlining zone from column 4 of tall spaces, a
        // double-width L at normal height over columns 5 and 6
        yield 'tall spaces after it, moved up' => [
            "\x1F\x4A\x41N\x1B\x4D \x1B\x5AS      \x1F\x4A\x45\x1B\x4EL",
            -5,
            -1,
        ];
        yield 'tall spaces after it' => ["\x1F\x4A\x41N\x1B\x4D \x1B\x5AS      \x1F\x4A\x45\x1B\x4EL", -5, 0];
    }

    #[DataProvider('zonesRestartedAtAWideCharactersRightHalf')]
    public function testOffsetStartsAZoneAgainAtTheRightHalfOfAWideCharacter(string $drawn, int $x, int $y): void
    {
        self::assertScreenMoved(new Vdt($drawn), $x, $y);
    }

    #[DataProvider('wideSpacesMovedOffTheLeft')]
    public function testOffsetWritesTheRightHalfOfAWideSpaceAsANormalWidthSpace(string $drawn, int $x): void
    {
        self::assertScreenMoved(new Vdt($drawn), $x, y: 0);
    }

    /**
     * Double-width and double-size spaces and delimiters in the last column, which shows their
     * left half only, moved left: that half is written as a space one column wide, and the
     * column after it stays as it was, as in the source (#152)
     *
     * @return iterable<string, array{string, int}> what is drawn, and x
     */
    public static function wideSpacesCutByTheLastColumn(): iterable
    {
        // Row 10: a green zone from column 38, then a double-width space in column 40
        yield 'a double-width space' => ["\x1F\x4A\x66\x1BR X\x1BN ", -2];
        // A red double-width delimiter in column 40, moved to column 39: column 40 stays empty
        yield 'a double-width delimiter' => ["\x1F\x4A\x68\x1BN\x1BQ ", -1];
        // The same in double size, on rows 9 and 10
        yield 'a double-size delimiter' => ["\x1F\x4A\x68\x1BO\x1BQ ", -3];
        // Not moved: written as it is, its left half in column 40
        yield 'a double-width delimiter in place' => ["\x1F\x4A\x68\x1BN\x1BQ ", 0];
        // Another character keeps its width: its left half shows what the source's does (what
        // its right half shows isn't compared, see assertScreenMoved())
        yield 'a double-width character' => ["\x1F\x4A\x68\x1BNX", -2];
    }

    #[DataProvider('wideSpacesCutByTheLastColumn')]
    public function testOffsetWritesTheLeftHalfOfAWideSpaceInTheLastColumnAsANormalWidthSpace(
        string $drawn,
        int $x,
    ): void {
        self::assertScreenMoved(new Vdt($drawn), $x, y: 0);
    }

    /**
     * CAN in double width or double size: its spaces are two columns wide, so they run on over
     * the next row (two rows down in double size), as text wraps (MiniPavi's emulator,
     * decoder.js, clear("eol")). Moved, each space is where the source's is, moved: none
     * runs on from the moved column, and the run-on part stays when the CAN's row goes off
     * the screen (#152)
     *
     * @return iterable<string, array{string, int, int}> what is drawn, x and y
     */
    public static function wideClearLinesMoved(): iterable
    {
        // Row 10, column 1: spaces over rows 10 and 11
        yield 'double width, in place' => ["\x1F\x4A\x41\x1BN\x18", 0, 0];
        yield 'double width, right' => ["\x1F\x4A\x41\x1BN\x18", 1, 0];
        yield 'double width, left' => ["\x1F\x4A\x41\x1BN\x18", -1, 0];
        yield 'double width, two left' => ["\x1F\x4A\x41\x1BN\x18", -2, 0];
        // Row 1: only its run-on part, on row 2, stays, moved onto row 1
        yield 'double width, its row off the screen' => ["\x1F\x41\x41\x1BN\x18", 0, -1];
        // Row 10, column 3, after AB: the spaces from column 3 to 40, then 1 to 36 of row 11
        yield 'double width, after text, right' => ["\x1F\x4A\x41\x1BNAB\x18", 3, 1];
        // ESC R: the first space a green delimiter
        yield 'double width, its delimiter left' => ["\x1F\x4A\x41\x1BN\x1BR\x18", -2, 0];
        yield 'double width, its delimiter right' => ["\x1F\x4A\x43\x1BN\x1BR\x18", 1, 0];
        // Row 10, column 1, in double size: rows 9 to 12
        yield 'double size, left' => ["\x1F\x4A\x41\x1BO\x18", -2, 0];
        yield 'double size, right' => ["\x1F\x4A\x41\x1BO\x18", 1, 0];
        yield 'double size, its delimiter, down' => ["\x1F\x4A\x41\x1BO\x1BQ\x18", -1, 1];
    }

    #[DataProvider('wideClearLinesMoved')]
    public function testOffsetMovesEachSpaceOfAWideClearLine(string $drawn, int $x, int $y): void
    {
        self::assertScreenMoved(new Vdt($drawn), $x, $y);
    }

    /**
     * CANs in double height on row 10, their spaces' top halves on row 9, the first one a
     * delimiter drawn there too (MiniPavi's emulator, decoder.js, printDelimiter()): its top
     * half still starts its zone on row 9 once its bottom half is drawn over, and the top
     * halves on row 9 show row 9's zone (#162)
     *
     * @return iterable<string, array{string, int, int}> what is drawn, x and y
     */
    public static function tallClearLinesMoved(): iterable
    {
        // ESC Q, ESC M, CAN: a red tall delimiter in column 1, drawn over on row 10 by a
        // double-width N: row 9 stays red
        $drawnOverWide = "\x1F\x4A\x41\x1B\x51\x1B\x4D\x18\x1B\x4EN";
        yield 'its delimiter drawn over on its row only, in place' => [$drawnOverWide, 0, 0];
        yield 'its delimiter drawn over on its row only, right' => [$drawnOverWide, 2, 0];
        yield 'its delimiter drawn over on its row only, down' => [$drawnOverWide, 1, 1];
        // The same, drawn over by an X at normal size (ESC L)
        yield 'its delimiter drawn over by a normal character' => ["\x1F\x4A\x41\x1B\x51\x1B\x4D\x18\x1B\x4CX", 0, 0];
        // From column 2, after A: moved left, its delimiter in column 1
        yield 'its delimiter drawn over on its row only, left' => [
            "\x1F\x4A\x41A\x1B\x51\x1B\x4D\x18\x1B\x4EN",
            -1,
            0,
        ];
        // ESC W, a white delimiter at normal height in column 1, then a tall CAN from column 2:
        // its top halves, on row 9, show no zone; moved left, the white zone starts again on
        // row 10 only
        yield 'after a delimiter at normal height, left' => ["\x1F\x4A\x41\x1B\x57 \x1B\x4D\x18", -5, 0];
        yield 'after a delimiter at normal height, left and down' => ["\x1F\x4A\x41\x1B\x52 \x1B\x4D\x18", -5, 1];
        // Row 9 green from column 1, then a tall CAN on row 10 from column 2: its top halves,
        // on row 9, in the green zone, which starts again there once moved left
        yield 'in the zone of the row above, left' => ["\x1F\x49\x41\x1B\x52\x18\x1F\x4A\x42\x1B\x4D\x18", -1, 0];
    }

    #[DataProvider('tallClearLinesMoved')]
    public function testOffsetShowsTheTopHalvesOfATallClearLine(string $drawn, int $x, int $y): void
    {
        self::assertScreenMoved(new Vdt($drawn), $x, $y);
    }

    /**
     * Tall cells drawn from row 24, moved down by one row: their top halves, on row 23, move
     * to row 24, their bottom halves off the screen. The Minitel draws a tall character from
     * its bottom row, so it can't show a top half alone on row 24 (MiniPavi's emulator,
     * decoder.js, printG0Char()): Offset writes it as a space at normal height, with its
     * attributes, which shows its background (or its colour, inverted) without its glyph,
     * rather than nothing, which would leave what was drawn there before it (#173)
     *
     * @return iterable<string, array{string, int, int}> what is drawn, x and y
     */
    public static function tallCellsWhoseBottomRowIsMovedOffTheScreen(): iterable
    {
        // Row 23: a red delimiter, then ABCDE; tall spaces from row 24 over C and D. Moved
        // left, the red zone starts again in the top half of the first one, in column 1
        yield 'tall spaces in a background' => ["\x1F\x57\x41\x1B\x51 ABCDE\x1F\x58\x44\x1B\x4D  ", -3, 1];
        // The same, underlined: the second space shows the underline of its zone, which the
        // top half of a tall space doesn't, as the Minitel draws it at the bottom of the
        // character, and a delimiter there would end the zone before E
        yield 'tall spaces in an underline' => ["\x1F\x57\x41\x1B\x5A ABCDE\x1F\x58\x44\x1B\x4D  ", -3, 1];
        // The same, a double-size L over C and D: its top half as a double-width space, in
        // which the red zone starts again
        yield 'a double-size character in a background' => ["\x1F\x57\x41\x1B\x51 ABCDE\x1F\x58\x44\x1B\x4FL", -3, 1];
        yield 'a double-size character in an underline' => ["\x1F\x57\x41\x1B\x5A ABCDE\x1F\x58\x44\x1B\x4FL", -3, 1];
        // Row 24: a red delimiter, AB, then tall spaces: the red zone, off the screen, isn't
        // written in their top halves, on row 23, which show no zone
        yield 'tall spaces after a zone of the row going off the screen' => [
            "\x1F\x58\x41\x1B\x51 AB\x1B\x4D  ",
            -3,
            1,
        ];
        // Row 24 moved left, its text at normal height: the red zone starts again in the space
        // after AB, as its characters are written as they are
        yield 'no tall cell, the zone starting again on the last row' => ["\x1F\x58\x41\x1B\x51 AB CD", -2, 0];
        // Moved down only: double-height A and B over XY
        yield 'tall characters over others' => ["\x1F\x57\x41XYZ\x1F\x58\x41\x1B\x4DAB", 0, 1];
        // Inverted, in double size: its colour, over two columns
        yield 'an inverted double-size character' => ["\x1F\x57\x41XYZ\x1F\x58\x41\x1B\x5D\x1B\x4FAB", 0, 1];
        // A red tall delimiter, whose top half starts the red zone on row 23 too
        yield 'a tall delimiter' => ["\x1F\x57\x41XYZ\x1F\x58\x41\x1B\x4D\x1B\x51 AB", 0, 1];
        // Moved right, a double-size A whose left half only stays, in column 40
        yield 'a double-size character cut by the last column' => ["\x1F\x57\x41XYZ\x1F\x58\x41\x1B\x4FAB", 39, 1];
        // Moved left, a double-size A whose right half only stays, in column 1
        yield 'a double-size character cut by the first column' => ["\x1F\x57\x41XYZ\x1F\x58\x41\x1B\x4FAB", -1, 1];
        // A tall CAN, its spaces' top halves on row 23
        yield 'a tall clear line' => ["\x1F\x57\x41XYZ\x1F\x58\x41\x1B\x51\x1B\x4D\x18", 0, 1];
        yield 'a tall clear line, left' => ["\x1F\x57\x41XYZ\x1F\x58\x42\x1B\x51\x1B\x4D\x18", -1, 1];
        // A REP before any character, in double height
        yield 'a tall repeat before any character' => ["\x1F\x57\x41XYZ\x1F\x58\x41\x1B\x4D\x12\x42", 0, 1];
    }

    #[DataProvider('tallCellsWhoseBottomRowIsMovedOffTheScreen')]
    public function testOffsetWritesTheTopHalfOfATallCellOnTheLastRowAsASpace(string $drawn, int $x, int $y): void
    {
        self::assertScreenMoved(new Vdt($drawn), $x, $y);
    }

    public function testOffsetWritesAClearLineMovedLeftAsSpaces(): void
    {
        // Row 10: A, ESC Z, CAN from column 2, moved to column 1: its delimiter, then spaces
        // up to column 39, as column 40 came from beyond the screen
        self::assertSame(
            "\x1F\x4A\x41\x1B\x5A \x12\x66",
            (new Offset(x: -1, y: 0))
                ->apply(new Vdt("\x1F\x4A\x41A\x1B\x5A\x18"))
                ->toString(),
        );
    }

    public function testOffsetWritesNoDelimiterForAZoneAClearScreenEnded(): void
    {
        // Row 10: an underlined zone, a clear screen, then a space in column 3 of row 10,
        // in no zone: written as it is, as nothing is left of the zone
        $vdt = "\x1F\x4A\x41\x1B\x5A AB\x0C\x1F\x4A\x43 C";

        self::assertSame(
            $vdt,
            (new Offset(x: 0, y: 0))
                ->apply(new Vdt($vdt))
                ->toString(),
        );
    }

    /**
     * A character repeated: REP once it is shorter, up to the end of its row (a row is
     * shorter than the most repetitions a REP gives, see
     * testRepeatsACharacterAsManyTimesAsAsked())
     *
     * @return iterable<string, array{string, string}> what is drawn from row 1, column 1, and
     *                                                 how it is written
     */
    public static function repetitions(): iterable
    {
        yield 'twice more, in full' => ['AAA', 'AAA'];
        yield 'three times more, with REP' => ['AAAA', "A\x12\x43"];
        // Positioned again on the next row
        yield 'over two rows' => [str_repeat('-', times: 42), "-\x12\x67\x1F\x42\x41--"];
        // A G2 character takes 3 bytes: REP from once more
        yield 'a G2 character' => ["\x19\x42e\x19\x42e", "\x19\x42e\x12\x41"];
        yield 'a G2 character after SYN' => ["\x16\x42e\x16\x42e", "\x16\x42e\x12\x41"];
    }

    #[DataProvider('repetitions')]
    public function testOffsetRepeatsACharacter(string $drawn, string $written): void
    {
        self::assertSame(
            "\x1F\x41\x41{$written}",
            (new Offset(x: 0, y: 0))
                ->apply(new Vdt("\x1F\x41\x41{$drawn}"))
                ->toString(),
        );
    }

    public function testOffsetWritesTheSpacesARepeatAfterAClearLineDraws(): void
    {
        // Row 2: AB, CAN, back to column 1, REP twice: two spaces over AB, as MiniPavi's
        // emulator repeats the last space CAN printed (#163), written as spaces
        self::assertSame(
            "\x1F\x42\x42AB\x18\x1F\x42\x42  ",
            (new Offset(x: 1, y: 0))
                ->apply(new Vdt("\x1F\x42\x41AB\x18\x08\x08\x12\x42"))
                ->toString(),
        );
    }

    /**
     * REP right after a CAN, which repeats a space (#163)
     *
     * @return iterable<string, array{string, int, int}> what is drawn, x and y
     */
    public static function repeatsAfterClearLineMoved(): iterable
    {
        // Row 10: AB, CAN, back over AB, REP
        yield 'at normal size, right' => ["\x1F\x4A\x41AB\x18\x08\x08\x12\x42", 2, 1];
        yield 'at normal size, left' => ["\x1F\x4A\x42AB\x18\x08\x08\x12\x42", -1, 0];
        yield 'from where CAN leaves the cursor' => ["\x1F\x4A\x41AB\x18\x12\x45", 1, 0];
        yield 'in double width' => ["\x1F\x4A\x41\x1BNAB\x18\x08\x08\x08\x08\x12\x42", 1, 0];
        yield 'in double width, left' => ["\x1F\x4A\x41\x1BNAB\x18\x08\x08\x08\x08\x12\x42", -1, 0];
        yield 'in double size' => ["\x1F\x4A\x41\x1BOAB\x18\x08\x08\x08\x08\x12\x42", 1, 1];
        // ESC T: the first space of CAN a blue delimiter, drawn over by the first space of REP
        yield 'over its delimiter' => ["\x1F\x4A\x41AB\x1BT\x18\x12\x43", 1, 0];
        yield 'over its delimiter, left' => ["\x1F\x4A\x41AB\x1BT\x18\x12\x43", -1, 0];
    }

    #[DataProvider('repeatsAfterClearLineMoved')]
    public function testOffsetMovesARepeatAfterAClearLine(string $drawn, int $x, int $y): void
    {
        self::assertScreenMoved(new Vdt($drawn), $x, $y);
    }

    /**
     * REP before any character, which repeats a space (#163)
     *
     * @return iterable<string, array{string, int, int}> what is drawn, x and y
     */
    public static function repeatsBeforeAnyCharacterMoved(): iterable
    {
        // Row 10: REP 3, then X in column 4
        yield 'at normal size, right' => ["\x1F\x4A\x41\x12\x43X", 1, 1];
        yield 'at normal size, left' => ["\x1F\x4A\x41\x12\x43X", -1, 0];
        yield 'in double width, left' => ["\x1F\x4A\x41\x1BN\x12\x42X", -1, 0];
        yield 'in double width, a delimiter, left' => ["\x1F\x4A\x41\x1BN\x1BT\x12\x42X", -1, 0];
        yield 'in double width in the last column, left' => ["\x1F\x4A\x68\x1BN\x1B]\x12\x41", -1, 0];
        // 45 times: 40 on row 10, 5 on row 11
        yield 'over two rows, down' => ["\x1F\x4A\x41\x12\x6D", 0, 1];
        yield 'over two rows, right and up' => ["\x1F\x4A\x41\x12\x6D", 1, -1];
        yield 'in double size, left' => ["\x1F\x4A\x41\x1BO\x12\x42X", -1, 0];
        yield 'in double size, right and down' => ["\x1F\x4A\x41\x1BO\x12\x42X", 1, 1];
        // ESC T: the first space a blue delimiter
        yield 'a delimiter, right' => ["\x1F\x4A\x41\x1BT\x12\x42X", 1, 0];
        yield 'a delimiter, left' => ["\x1F\x4A\x41\x1BT\x12\x42X", -1, 0];
    }

    /**
     * REP before any character repeats the character printed before the content, which can't
     * be known (the gateway's PIN, the previous page's last character): written with REP too,
     * so that it shows the same, whatever it is (#163)
     *
     * @return iterable<string, array{string, int, string}> what is drawn from row 10, column 1,
     *                                                     x, and how it is written
     */
    public static function repeatsBeforeAnyCharacterWritten(): iterable
    {
        yield 'moved right' => ["\x12\x43X", 1, "\x1F\x4A\x42\x12\x43X"];
        // ESC T waiting: the first one a blue delimiter, then X and a space in its zone
        yield 'a zone attribute waiting, then text' => ["\x1BT\x12\x42X Y", 1, "\x1F\x4A\x42\x1BT\x12\x42X Y"];
        // The first one off the screen
        yield 'moved left' => ["\x12\x43X", -1, "\x1F\x4A\x41\x12\x42X"];
        // Then a space, written as one
        yield 'then a space' => ["\x12\x42 Y", 1, "\x1F\x4A\x42\x12\x42 Y"];
        yield 'in mosaic' => ["\x0E\x12\x42", 1, "\x1F\x4A\x42\x0E\x12\x42"];
        // 45 times: 40 on row 10, 5 on row 11; moved right, the 40th goes off the screen
        yield 'over two rows' => ["\x12\x6D", 1, "\x1F\x4A\x42\x12\x67\x1F\x4B\x42\x12\x45"];
        // ESC T waiting: written before REP, as in the content (a delimiter if it is a space)
        yield 'a zone attribute waiting' => ["\x1BT\x12\x42X", 1, "\x1F\x4A\x42\x1BT\x12\x42X"];
        // Its first one, which a blue zone starts at, off the screen: the zone starts again at
        // the second one, ESC T written before it
        yield 'a zone attribute waiting, moved left' => ["\x1BT\x12\x42X", -1, "\x1F\x4A\x41\x1BT\x12\x41X"];
        // The first one's right half in column 1: the Minitel can't show it alone, so it is
        // written one column wide with REP, which keeps repeating the character printed
        // before (a space written there would be what REP repeats next)
        yield 'in double width, moved left' => [
            "\x1BN\x12\x42X",
            -1,
            "\x1F\x4A\x41\x12\x41\x1BN\x12\x41X",
        ];
        // ESC T waiting: the first one, a delimiter if it is a space, written one column wide
        // after ESC T, which waits otherwise, as in the content
        yield 'in double width, a zone attribute waiting, moved left' => [
            "\x1BN\x1BT\x12\x42X",
            -1,
            "\x1F\x4A\x41\x1BT\x12\x41\x1BN\x12\x41X",
        ];
        // 21 times: 20 on row 10, then one on row 11, each first one's right half in column 1
        yield 'in double width, moved left, over two rows' => [
            "\x1BN\x12\x55X",
            -1,
            "\x1F\x4A\x41\x12\x41\x1BN\x12\x53\x1F\x4B\x41\x12\x41\x1BNX",
        ];
        // In column 40, inverted, where the Minitel shows its left half: moved left, written
        // one column wide, as a space there is (a wide one would show one column more)
        yield 'in double width in the last column, moved left' => [
            "\x1F\x4A\x68\x1BN\x1B]\x12\x41",
            -1,
            "\x1F\x4A\x67\x1B]\x12\x41",
        ];
        // The REP after a clear screen still repeats the character printed before (its one
        // character off the screen)
        yield 'in double width, moved left, then a clear screen' => [
            "\x1BN\x12\x42\x0C\x1F\x4A\x41\x12\x42",
            -1,
            "\x1F\x4A\x41\x12\x41\x1BN\x12\x41\x0C\x1F\x4A\x41\x12\x41",
        ];
    }

    #[DataProvider('repeatsBeforeAnyCharacterWritten')]
    public function testOffsetWritesARepeatBeforeAnyCharacterWithRep(string $drawn, int $x, string $written): void
    {
        self::assertSame(
            $written,
            (new Offset($x, y: 0))
                ->apply(new Vdt("\x1F\x4A\x41{$drawn}"))
                ->toString(),
        );
    }

    #[DataProvider('repeatsBeforeAnyCharacterMoved')]
    public function testOffsetMovesARepeatBeforeAnyCharacter(string $drawn, int $x, int $y): void
    {
        self::assertScreenMoved(new Vdt($drawn), $x, $y);
    }

    public function testOffsetSetsTheAttributesBackAfterAClearScreen(): void
    {
        // Red X at row 1, column 1, a clear screen, then Y there, white: nothing to send again
        self::assertSame(
            "\x1F\x41\x41\x1B\x41X\x0CY",
            (new Offset(x: 0, y: 0))
                ->apply(new Vdt("\x1B\x41X\x0CY"))
                ->toString(),
        );
    }

    public function testOffsetDropsWhatGoesOffTheScreenInsteadOfWrapping(): void
    {
        $abcde = new Vdt("\x1F\x41\x41ABCDE");

        self::assertSame('CDE', rtrim(Screen::of((new Offset(x: -2, y: 0))->apply($abcde))->line(1)));
        self::assertSame(
            str_repeat(' ', times: 37) . 'ABC',
            Screen::of((new Offset(x: 37, y: 0))->apply($abcde))->line(1),
        );
        self::assertSame('', trim(Screen::of((new Offset(x: 37, y: 0))->apply($abcde))->line(2)));
        self::assertSame('', trim(Screen::of((new Offset(x: 0, y: -1))->apply(new Vdt("\x1F\x41\x41ABC")))->text()));
    }

    #[DataProviderExternal(SequencesTest::class, 'sequencesDrawingNothing')]
    public function testOffsetKeepsTheSequencesThatDrawNothing(string $sequence): void
    {
        $moved = (new Offset(x: 1, y: 1))
            ->apply(new Vdt("\x1F\x41\x41{$sequence}A"))
            ->toString();

        // Sent whole, and only the A after it shown, moved
        self::assertStringContainsString($sequence, $moved);
        $screen = Screen::of(new Vdt($moved));
        self::assertSame('A', trim($screen->text()));
        self::assertStringStartsWith(' A ', $screen->line(2));
    }

    #[DataProviderExternal(CursorTest::class, 'positionsOutsideTheScreen')]
    public function testOffsetDropsAPositioningTheMinitelIgnores(string $position): void
    {
        // Row 5: red A, B, the positioning, then C, drawn after B, still red
        self::assertSame(
            "\x1F\x46\x43\x1B\x41ABC",
            (new Offset(x: 2, y: 1))
                ->apply(new Vdt("\x1F\x45\x41\x1B\x41AB{$position}C"))
                ->toString(),
        );
    }

    public function testOffsetDoesNotMoveTheStatusRow(): void
    {
        $moved = Screen::of((new Offset(x: 1, y: 1))->apply(new Vdt("\x1F\x40\x41status\x1F\x41\x41X")));

        self::assertSame('status', rtrim($moved->line(0)));
        self::assertSame(' X', rtrim($moved->line(2)));
    }

    public function testOffsetMovesWhatALineFeedFromTheStatusRowDraws(): void
    {
        // X at row 10, column 7, L on row 0, then LF: M back where the cursor was before row 0,
        // in red, as set before it
        $moved = (new Offset(x: 2, y: 3))->apply(new Vdt("\x1F\x4A\x47\x1B\x41X\x1F\x40\x45L\x0AM"));

        self::assertSame("\x1F\x4D\x49\x1B\x41X\x1F\x40\x45L\x1F\x4D\x4A\x1B\x41M", $moved->toString());
    }

    /**
     * A double-height or double-size character moved onto row 1, where the Minitel can't draw
     * double height (#172): written at normal height, its double width kept
     *
     * @return iterable<string, array{string, string}>
     */
    public static function tallCharactersMovedOntoRow1(): iterable
    {
        // Double width kept: A in columns 1-2, B in columns 3-4, as before
        yield 'double size' => ["\x1B\x4FAB", "\x1F\x41\x41\x1B\x4EAB"];
        // ESC O after ESC N: B double width too, as drawn on row 1 (ESC O discarded there)
        yield 'double size after double width' => ["\x1B\x4EA\x1B\x4FB", "\x1F\x41\x41\x1B\x4EAB"];
        // One column each already
        yield 'double height' => ["\x1B\x4DAB", "\x1F\x41\x41AB"];
    }

    #[DataProvider('tallCharactersMovedOntoRow1')]
    public function testOffsetWritesATallCharacterMovedOntoRow1AtNormalSize(string $row3, string $written): void
    {
        self::assertSame(
            $written,
            (new Offset(x: 0, y: -2))
                ->apply(new Vdt("\x1F\x43\x41" . $row3))
                ->toString(),
        );
    }

    public function testOffsetKeepsDoubleHeightMovedOntoRow2(): void
    {
        self::assertSame(
            "\x1F\x42\x41\x1B\x4DA",
            (new Offset(x: 0, y: -1))
                ->apply(new Vdt("\x1F\x43\x41\x1B\x4DA"))
                ->toString(),
        );
    }

    public function testDoubleWidthTakesTwoColumnsInTextButNotInMosaic(): void
    {
        $text = Screen::of(new Vdt("\x1F\x41\x41\x1B\x4EAB"));
        $mosaic = Screen::of(new Vdt("\x1F\x41\x41\x0E\x1B\x4E\x21\x22"));

        self::assertSame(['A', 'A', 'B', 'B'], array_map(static fn(int $col) => $text->cell(1, $col)?->bytes, [
            1,
            2,
            3,
            4,
        ]));
        self::assertSame(
            ["\x21", "\x22", null],
            array_map(static fn(int $col) => $mosaic->cell(1, $col)?->bytes, [1, 2, 3]),
        );
    }

    /**
     * background.vdt: a page made with a Videotex editor; features.vdt: made
     * by hand to have every kind of token (status row, CAN with a background color, double
     * width, mosaic and repetitions, G2, wrapping at column 40, BS/LF/CR/HT/VT, US in digits)
     *
     * @return iterable<string, array{string, int, int}>
     */
    public static function offsets(): iterable
    {
        foreach (['background', 'features'] as $file) {
            yield "{$file}, none" => [$file, 0, 0];
            yield "{$file}, right and up" => [$file, 8, -2];
            yield "{$file}, left and down" => [$file, -5, 3];
            yield "{$file}, mostly off the screen" => [$file, 30, 20];
            yield "{$file}, one column left" => [$file, -1, 0];
        }
    }

    /**
     * The screen drawn by the moved content is the original one, moved: every cell is found
     * x columns right and y rows down, with the same character and attributes, except on
     * row 0 (the status row), which stays as it is; nothing else is drawn (see
     * assertScreenMoved() for what shows of a double-width character partly off the screen)
     */
    #[DataProvider('offsets')]
    public function testOffsetDrawsTheSameScreenMoved(string $file, int $x, int $y): void
    {
        self::assertScreenMoved(self::fixture($file), $x, $y);
    }

    /**
     * The screen drawn by the original content, moved by offset(x, y), is the original one, moved:
     * every cell is found x columns right and y rows down, with the same character and attributes,
     * except on row 0 (the status row), which stays as it is; nothing else is drawn. A double-width
     * character moved partly off the screen on the right shows its left half in the last column, as
     * the Minitel shows one written there (MiniPavi's emulator, decoder.js, printG0Char()); moved
     * partly off on the left, its right half shows as a space one column wide (narrowed()) does: a
     * space's right half as it is, another character's without its glyph (its background, or its
     * colour when inverted), as the Minitel can't show a right half alone. The right half the
     * Minitel doesn't show of a character other than a space in the last column, moved left, isn't
     * compared: it is written whole. On a row moved left, a zone whose delimiter goes off the
     * screen can only start again at a space (Writer): on a row whose text space went off the
     * screen, the cells before the first one left show what they do without it
     * (withoutZoneFromOffTheScreen()), and that space as its delimiter does: its background,
     * neither underlined nor masked (asADelimiter()); the right half of a wide character in
     * the column moved to column 1, written as a space, is that space (#170).
     * Each cell is compared as it shows (asShown()): a tall character's top half without its
     * underline, which the Minitel draws in the bottom half only, and each half of a tall
     * space as the space at normal height it shows as
     */
    private static function assertScreenMoved(Vdt $original, int $x, int $y): void
    {
        self::assertShowsMoved(Screen::of($original), Screen::of((new Offset($x, $y))->apply($original)), $x, $y);
    }

    /**
     * The after screen shows the before one, moved by x columns right and y rows down, as
     * assertScreenMoved() says
     */
    private static function assertShowsMoved(Screen $before, Screen $after, int $x, int $y): void
    {
        $before = self::withTopHalvesAsSpaces($before, ScreenSize::ROWS - $y);
        $glyphs = self::glyphColumns($before);

        for ($row = 0; $row <= ScreenSize::ROWS; $row++) {
            for ($col = 1; $col <= ScreenSize::COLS; $col++) {
                if ($row === 0) {
                    // The status line isn't moved
                    self::assertEquals($before->cell(0, $col), $after->cell(0, $col), "row 0, column {$col}");
                    continue;
                }
                [$fromRow, $fromCol] = [$row - $y, $col - $x];
                [$start, $width] = $glyphs[$fromRow][$fromCol] ?? [$fromCol, 1];
                $onScreen = $fromRow >= 1 && $fromRow <= ScreenSize::ROWS && ($start + $x) >= 1;
                $source = $before->cell($fromRow, $fromCol);
                $expected = $onScreen ? $source : null;
                $actual = $after->cell($row, $col);
                if ($x < 0 && $fromCol === ScreenSize::COLS && ($start + $width - 1) > ScreenSize::COLS) {
                    // The left half of a double-width character in the last column, moved left
                    if ($source?->bytes === ' ') {
                        [$expected, $actual] = [self::narrowed($source), self::narrowed($actual)];
                    }
                }
                if ($x < 0 && $fromCol === (ScreenSize::COLS + 1)) {
                    $cut = $before->cell($fromRow, ScreenSize::COLS);
                    if (
                        $cut !== null
                        && $cut->bytes !== ' '
                        && $cut->attributes->width() === 2
                        && !$cut->part->isRight()
                    ) {
                        // The right half the Minitel doesn't show of a character in the last column
                        continue;
                    }
                }
                if ($fromRow >= 1 && $fromRow <= ScreenSize::ROWS && ($start + $x) < 1) {
                    // The right half of a character, which the Minitel can't show alone: as a
                    // space one column wide, with its attributes
                    $space = $source === null ? null : new Cell(' ', $source->attributes, $source->part);
                    [$expected, $actual] = [self::narrowed($space), self::narrowed($actual)];
                }
                if (
                    $x < 0
                    && self::losesADelimiterOffTheScreen($before, $fromRow, firstCol: 1 - $x)
                    && self::isUpToTheRestart($before, $fromRow, $start, firstCol: 1 - $x)
                ) {
                    $shown = self::isTheRestart($before, $fromRow, $fromCol, firstCol: 1 - $x)
                        ? self::asADelimiter(...)
                        : self::withoutZoneFromOffTheScreen(...);
                    [$expected, $actual] = [$shown($expected), $shown($actual)];
                }
                self::assertEquals(self::asShown($expected), self::asShown($actual), "row {$row}, column {$col}");
            }
        }
    }

    /**
     * The screen, the top halves of the tall cells on row, whose bottom row is moved off the
     * screen, below the last row, each as a space at normal height with its attributes: its
     * background (or its colour, inverted) and its zone's underline, without its glyph. The
     * Minitel can't show a top half alone on the last row, as it draws a tall character from
     * its bottom row: Offset writes such a space instead, in which a zone can start again
     * (#173)
     */
    private static function withTopHalvesAsSpaces(Screen $screen, int $row): Screen
    {
        $cells = $screen->cells;
        foreach ($cells[$row] ?? [] as $col => $cell) {
            if ($cell === null || $cell->attributes->height() === 1 || $cell->part->isBottom()) {
                continue;
            }
            $cells[$row][$col] = new Cell(' ', $cell->attributes->flattened(), $cell->part);
        }

        return new Screen($cells);
    }

    /**
     * The cell as it shows. The top half of a tall (text) cell shows no underline: the
     * Minitel draws it at the bottom of the character, in its bottom half only (MiniPavi's
     * emulator, font-sprite.js, writeChar()), so a tall delimiter's top half shows as an
     * underlined top half does (#168). Each half of a tall text space shows as a space at
     * normal height does, its background, and the bottom half its underline at the bottom of
     * the cell. Offset writes such a space where a zone starts again over a tall space's
     * bottom half, leaving its top half on the row above, or over its top half, leaving its
     * bottom half (#153)
     */
    private static function asShown(?Cell $cell): ?Cell
    {
        if ($cell === null || $cell->attributes->height() === 1) {
            return $cell;
        }
        $attributes = $cell->part->isBottom()
            ? $cell->attributes
            : $cell->attributes->withUnderlining(Underlining::NoUnderline);
        if ($cell->bytes !== ' ') {
            return new Cell($cell->bytes, $attributes, $cell->part);
        }

        return new Cell($cell->bytes, $attributes->flattened(), new Part($cell->part->isRight(), bottom: false));
    }

    /**
     * The cell as a space one column wide shows: the right half of a double-width space shows
     * as a space at normal width does, its background, and its underline across the cell
     * (MiniPavi's emulator, font-sprite.js, writeChar()). Offset writes such a space where only
     * the right half of a double-width one stays on the screen
     */
    private static function narrowed(?Cell $cell): ?Cell
    {
        if ($cell === null) {
            return null;
        }

        return new Cell(
            $cell->bytes,
            $cell->attributes->narrowed(),
            new Part(right: false, bottom: $cell->part->isBottom()),
        );
    }

    /**
     * Whether the row, moved left so that firstCol is its first column, has a text space (a
     * delimiter may be one) that goes off the screen
     */
    private static function losesADelimiterOffTheScreen(Screen $screen, int $row, int $firstCol): bool
    {
        for ($col = 1; $col < $firstCol; $col++) {
            if (self::isTextSpace($screen->cell($row, $col))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the cell at row, col comes before the first cell of its row a zone can start
     * again in, once moved left so that firstCol is its column 1, or is that cell
     * (isTheRestart())
     */
    private static function isUpToTheRestart(Screen $screen, int $row, int $col, int $firstCol): bool
    {
        for ($before = $firstCol; $before < $col; $before++) {
            if (self::isTheRestart($screen, $row, $before, $firstCol)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether a zone can start again in the cell at row, col, once moved left so that
     * firstCol is its column 1: a text space, or the right half of a double-width character
     * in firstCol, which Offset writes as a space (#170)
     */
    private static function isTheRestart(Screen $screen, int $row, int $col, int $firstCol): bool
    {
        $cell = $screen->cell($row, $col);

        return self::isTextSpace($cell) || $col === $firstCol && $cell?->part->isRight() === true;
    }

    // Whether the cell shows a text space (a delimiter is one)
    private static function isTextSpace(?Cell $cell): bool
    {
        return $cell?->bytes === ' ' && !$cell->attributes->isMosaic();
    }

    /**
     * The cell as the delimiter starting its zone again shows: its background, but neither
     * underlined, as a delimiter isn't (Writer), nor masked, which shows nothing more on a
     * space (#170)
     */
    private static function asADelimiter(?Cell $cell): ?Cell
    {
        if ($cell === null) {
            return null;
        }
        $attributes = $cell->attributes->withUnderlining(Underlining::NoUnderline)->withMasking(Masking::Unmask);

        return new Cell($cell->bytes, $attributes, $cell->part);
    }

    /**
     * A cell without the zone attributes it may have from a zone started off the screen: its
     * masking, and in text its underline and background, unless a mosaic or empty cell
     * before it gives it (compared apart, as the cells around them are)
     */
    private static function withoutZoneFromOffTheScreen(?Cell $cell): ?Cell
    {
        if ($cell === null) {
            return null;
        }
        $zone = $cell->attributes->isMosaic() ? Zone::none()->on($cell->attributes->background()) : Zone::none();

        return $cell->inZone($zone);
    }

    /**
     * For each cell of the screen a character takes: the first column of that character, and
     * its width. A double-width text character takes two cells: the cell showing its right
     * half (Part) is in the column after its first one, even when its left half was drawn
     * over since.
     *
     * @return array<int, array<int, array{int, int}>>
     */
    private static function glyphColumns(Screen $screen): array
    {
        $columns = [];
        for ($row = 0; $row <= ScreenSize::ROWS; $row++) {
            for ($col = 1; $col <= ScreenSize::COLS; $col++) {
                $cell = $screen->cell($row, $col);
                $columns[$row][$col] = [
                    $cell?->part->isRight() === true ? $col - 1 : $col,
                    $cell?->attributes->width() ?? 1,
                ];
            }
        }

        return $columns;
    }

    /**
     * Byte-for-byte output, to notice any change in how the content is written
     */
    #[DataProvider('offsets')]
    public function testOffsetMatchesTheGoldenFile(string $file, int $x, int $y): void
    {
        $golden = self::FIXTURES . "/{$file}.offset_{$x}_{$y}.vdt";
        $vdt = (new Offset($x, $y))
            ->apply(self::fixture($file))
            ->toString();

        if (getenv('UPDATE_GOLDEN') === '1') {
            file_put_contents($golden, $vdt);
        }
        self::assertFileExists($golden, self::GOLDEN_MISSING);
        self::assertSame((string) file_get_contents($golden), $vdt);
    }

    /**
     * Content of a .vdt file of the fixtures
     */
    private static function fixture(string $name): Vdt
    {
        return new Vdt((string) file_get_contents(self::FIXTURES . "/{$name}.vdt"));
    }
}
