<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt;

use MiniPaviService\InvalidValueException;
use MiniPaviService\Minitel\Color;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\ScreenSize;
use MiniPaviService\Tests\Vdt\Screen\ScreenTest;
use MiniPaviService\Vdt\Offset;
use MiniPaviService\Vdt\Screen\Screen;
use MiniPaviService\Vdt\Vdt;
use MiniPaviService\Vdt\Videotex;
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
     * @return iterable<string, array{string, int, string}> content (after positioning at row
     *                                                     10, column 1), x, and the underline
     *                                                     of columns 1 to 6 once moved by x
     */
    public static function spacesKeepingTheirZone(): iterable
    {
        $red = Videotex::backgroundColor(Color::Red);
        $green = Videotex::backgroundColor(Color::Green);
        $back = str_repeat(Videotex::CURSOR_LEFT, times: 3);

        // Written again after a positioning, the background waits: the space is a delimiter,
        // of the zone it was in
        yield 'a background sent again after a positioning' => ["{$red}\x1B\x5A ABCD{$back} ", 0, '.U.UU '];
        // A background set in mosaic is sent in text
        yield 'a background set in mosaic' => ["\x1B\x5A AB\x0E{$green}\x0F CD", 0, '.UU.UU'];
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

    #[DataProviderExternal(ScreenTest::class, 'sequencesDrawingNothing')]
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

    public function testOffsetDoesNotMoveTheStatusRow(): void
    {
        $moved = Screen::of((new Offset(x: 1, y: 1))->apply(new Vdt("\x1F\x40\x41status\x1F\x41\x41X")));

        self::assertSame('status', rtrim($moved->line(0)));
        self::assertSame(' X', rtrim($moved->line(2)));
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
     * row 0 (the status row), which stays as it is; nothing else is drawn. A double-width
     * character that would be partly off the screen is not drawn at all.
     */
    #[DataProvider('offsets')]
    public function testOffsetDrawsTheSameScreenMoved(string $file, int $x, int $y): void
    {
        $original = self::fixture($file);
        $before = Screen::of($original);
        $after = Screen::of((new Offset($x, $y))->apply($original));
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
                $onScreen =
                    $fromRow >= 1
                    && $fromRow <= ScreenSize::ROWS
                    && ($start + $x) >= 1
                    && ($start + $x + $width - 1) <= ScreenSize::COLS;
                $expected = $onScreen ? $before->cell($fromRow, $fromCol) : null;
                self::assertEquals($expected, $after->cell($row, $col), "row {$row}, column {$col}");
            }
        }
    }

    /**
     * For each cell of the screen a character takes: the first column of that character, and
     * its width. A double-width text character takes two cells, read from the left: its second
     * one only if it still shows it (a character drawn over it since takes that cell). A
     * second half left alone, its first one drawn over, reads as a character of its own.
     *
     * @return array<int, array<int, array{int, int}>>
     */
    private static function glyphColumns(Screen $screen): array
    {
        $columns = [];
        for ($row = 0; $row <= ScreenSize::ROWS; $row++) {
            for ($col = 1; $col <= ScreenSize::COLS; $col++) {
                $cell = $screen->cell($row, $col);
                $width = $cell?->attributes->width() ?? 1;
                $columns[$row][$col] = [$col, $width];
                $next = $screen->cell($row, $col + 1);
                if ($width === 2 && $next?->bytes === $cell?->bytes && $next?->attributes->width() === 2) {
                    $columns[$row][$col + 1] = [$col, $width];
                    $col++;
                }
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
