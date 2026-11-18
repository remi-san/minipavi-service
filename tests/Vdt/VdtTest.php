<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt;

use MiniPaviService\Page\Display\Color;
use MiniPaviService\Vdt\Player;
use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Cursor;
use MiniPaviService\Vdt\Screen\Screen;
use MiniPaviService\Vdt\ScreenListener;
use MiniPaviService\Vdt\Token;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class VdtTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../fixtures/vdt';

    // PHPUnit's assertions take no named arguments: their messages are constants
    private const GOLDEN_MISSING = 'Run the tests with UPDATE_GOLDEN=1 to write it';

    public function testTextShowsItsAccents(): void
    {
        self::assertStringStartsWith('Déjà vu ', Vdt::text('Déjà vu')->screen()->line(1));
        // Control characters can't slip in as text: a clear screen would erase the "a"
        self::assertStringStartsWith('a b ', Vdt::text("a\x0Cb")->screen()->line(1));
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

    public function testKeepsWhatShowsFromTheLastClearScreen(): void
    {
        $vdt = new Vdt("\x1F\x41\x41A\x0C\x1F\x41\x41B\x0C\x1F\x42\x41C");

        self::assertSame("\x0C\x1F\x42\x41C", $vdt->fromLastClear()->toString());
        // Without a clear screen: all of it
        self::assertSame("\x1F\x41\x41A", (new Vdt("\x1F\x41\x41A"))->fromLastClear()->toString());
    }

    public function testGivesBackItsBytesAsTheyAre(): void
    {
        $vdt = (string) file_get_contents(self::FIXTURES . '/background.vdt');

        self::assertSame($vdt, (new Vdt($vdt))->toString());
        // Equal by its bytes, drawn or not
        $drawn = new Vdt("\x1F\x41\x41Hi");
        $drawn->screen();
        self::assertEquals(new Vdt("\x1F\x41\x41Hi"), $drawn);
    }

    public function testOffsetMovesTextAndKeepsItsAttributes(): void
    {
        // HELLO on row 1, then four red A (the last three as a repetition) on row 2
        $moved = (new Vdt("\x1F\x41\x41HELLO\x1F\x42\x41\x1B\x41AA\x12\x42"))
            ->offset(x: 2, y: 3)
            ->screen();

        self::assertSame('  HELLO', rtrim($moved->line(4)));
        self::assertSame('  AAAA', rtrim($moved->line(5)));
        // Still red, after the positioning, which resets the attributes
        foreach ([3, 6] as $col) {
            self::assertSame(Color::Red, $moved->cell(row: 5, col: $col)?->attributes->foreground());
        }
        self::assertSame('', trim($moved->line(1) . $moved->line(2)));
    }

    public function testOffsetDropsWhatGoesOffTheScreenInsteadOfWrapping(): void
    {
        $abcde = new Vdt("\x1F\x41\x41ABCDE");

        self::assertSame('CDE', rtrim($abcde->offset(x: -2, y: 0)->screen()->line(1)));
        self::assertSame(str_repeat(' ', times: 37) . 'ABC', $abcde->offset(x: 37, y: 0)->screen()->line(1));
        self::assertSame('', trim($abcde->offset(x: 37, y: 0)->screen()->line(2)));
        self::assertSame(
            '',
            trim(
                (new Vdt("\x1F\x41\x41ABC"))
                    ->offset(x: 0, y: -1)
                    ->screen()
                    ->text(),
            ),
        );
    }

    public function testOffsetDoesNotMoveTheStatusRow(): void
    {
        $moved = (new Vdt("\x1F\x40\x41status\x1F\x41\x41X"))
            ->offset(x: 1, y: 1)
            ->screen();

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
            (new Vdt("\x1F\x43\x41" . $row3))
                ->offset(x: 0, y: -2)
                ->toString(),
        );
    }

    public function testOffsetKeepsDoubleHeightMovedOntoRow2(): void
    {
        self::assertSame(
            "\x1F\x42\x41\x1B\x4DA",
            (new Vdt("\x1F\x43\x41\x1B\x4DA"))
                ->offset(x: 0, y: -1)
                ->toString(),
        );
    }

    public function testDoubleWidthTakesTwoColumnsInTextButNotInMosaic(): void
    {
        $text = (new Vdt("\x1F\x41\x41\x1B\x4EAB"))->screen();
        $mosaic = (new Vdt("\x1F\x41\x41\x0E\x1B\x4E\x21\x22"))->screen();

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

    public function testWithoutClearDrawsOverWhatIsShown(): void
    {
        $screen = (new Vdt("\x1F\x43\x41shown"))
            ->append((new Vdt("\x0CAB"))->withoutClear())
            ->screen();

        self::assertSame('shown', rtrim($screen->line(3)));
        // From the top left corner, as after a clear screen
        self::assertSame('AB', rtrim($screen->line(1)));
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
        $before = $original->screen();
        $after = $original->offset($x, $y)->screen();
        $glyphs = self::glyphColumns($original);

        for ($row = 0; $row <= Screen::ROWS; $row++) {
            for ($col = 1; $col <= Screen::COLS; $col++) {
                if ($row === 0) {
                    // The status line isn't moved
                    self::assertEquals($before->cell(0, $col), $after->cell(0, $col), "row 0, column {$col}");
                    continue;
                }
                [$fromRow, $fromCol] = [$row - $y, $col - $x];
                [$start, $width] = $glyphs[$fromRow][$fromCol] ?? [$fromCol, 1];
                $onScreen =
                    $fromRow >= 1
                    && $fromRow <= Screen::ROWS
                    && ($start + $x) >= 1
                    && ($start + $x + $width - 1) <= Screen::COLS;
                $expected = $onScreen ? $before->cell($fromRow, $fromCol) : null;
                self::assertEquals($expected, $after->cell($row, $col), "row {$row}, column {$col}");
            }
        }
    }

    /**
     * For each cell drawn by a character: the first column of that character, and its width
     *
     * @return array<int, array<int, array{int, int}>>
     */
    private static function glyphColumns(Vdt $vdt): array
    {
        $listener = new class implements ScreenListener {
            /** @var array<int, array<int, array{int, int}>> */
            public array $columns = [];

            public function glyph(int $row, int $col, string $bytes, int $width, Attributes $attributes): void
            {
                for ($i = 0; $i < $width; $i++) {
                    $this->columns[$row][$col + $i] = [$col, $width];
                }
            }

            public function sequence(Token $sequence, Cursor $cursor): void {}
        };
        (new Player())->play($vdt, $listener);

        return $listener->columns;
    }

    /**
     * Byte-for-byte output, to notice any change in how the content is written
     */
    #[DataProvider('offsets')]
    public function testOffsetMatchesTheGoldenFile(string $file, int $x, int $y): void
    {
        $golden = self::FIXTURES . "/{$file}.offset_{$x}_{$y}.vdt";
        $vdt = self::fixture($file)->offset($x, $y)->toString();

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
