<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Page\Display;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\ScreenSize;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Page\Display\Size;
use MiniPaviService\Page\Display\Text;
use MiniPaviService\Vdt\Screen\Screen;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A text with a background colour, as a Minitel shows it. The background colour of text is a
 * zone attribute: it starts at a delimiter (a space) and runs along the row up to the next
 * one. So the text is written after a space, in its size, one character before it, or at the
 * start of its row, moving the text one character right; and, when it doesn't fill its row,
 * ends with a space on black, so that the colour doesn't run over what the row already shows
 * (MiniPavi's emulator, #92: each case checked on its screen)
 */
final class TextTest extends TestCase
{
    private const ROW = 10;

    /**
     * @return iterable<string, array{Text, string, string}> the text, then row 10 as shown
     *                                                       (rtrim()), and the background
     *                                                       of each cell from column 1: R
     *                                                       red, . black, ' ' empty
     */
    public static function texts(): iterable
    {
        yield 'filled, at column 10' => [
            self::text('Rouge pas', col: 10, fill: true),
            '         Rouge pas',
            '        ' . str_repeat('R', times: 32),
        ];
        // The space before the text in column 1, the text from column 2
        yield 'filled, at column 1' => [
            self::text('Rouge pas', col: 1, fill: true),
            ' Rouge pas',
            str_repeat('R', times: 40),
        ];
        yield 'at column 10' => [
            self::text('Sans fill', col: 10),
            '         Sans fill',
            '        RRRRRRRRRR.',
        ];
        yield 'at column 1' => [self::text('Sans', col: 1), ' Sans', 'RRRRR.'];
        // Centered from column 15, 11 columns wide, the space before it in column 14
        yield 'centered' => [
            self::text('Centre sans', centered: true),
            str_repeat(' ', times: 14) . 'Centre sans',
            str_repeat(' ', times: 13) . 'RRRRRRRRRRRR.',
        ];
        yield 'centered, filled' => [
            self::text('Centre fill', centered: true, fill: true),
            str_repeat(' ', times: 14) . 'Centre fill',
            str_repeat('R', times: 40),
        ];
        // Ending in the last column, it has no space after it
        yield 'up to the last column' => [
            self::text('Fin40', col: 36),
            str_repeat(' ', times: 35) . 'Fin40',
            str_repeat(' ', times: 34) . 'RRRRRR',
        ];
        // A column per character, not per byte: the space after it in the last column
        yield 'accented, up to the column before the last' => [
            self::text('Été', col: 37),
            str_repeat(' ', times: 36) . 'Été',
            str_repeat(' ', times: 35) . 'RRRR.',
        ];
        // A double-width space in columns 8 and 9, and one after the text
        yield 'double width' => [
            self::text('Sans', col: 10, size: Size::DoubleWidth),
            '         SSaannss',
            '       RRRRRRRRRR..',
        ];
        yield 'double width, filled' => [
            self::text('Rouge', col: 10, size: Size::DoubleWidth, fill: true),
            '         RRoouuggee',
            '       ' . str_repeat('R', times: 33),
        ];
        // No room for a double-width space before the text: it moves to column 3
        yield 'double width, at column 2' => [
            self::text('Sans', col: 2, size: Size::DoubleWidth),
            '  SSaannss',
            'RRRRRRRRRR..',
        ];
        yield 'double height' => [
            self::text('Sans', col: 10, size: Size::DoubleHeight),
            '         Sans',
            '        RRRRR.',
        ];
        yield 'double size' => [
            self::text('Sans', col: 10, size: Size::Double),
            '         SSaannss',
            '       RRRRRRRRRR..',
        ];
        // Without a background colour, the text is all there is
        yield 'no background' => [
            new Text('Blanc', new Position(self::ROW, 10), false, Color::White, null, null, false),
            '         Blanc',
            '         .....',
        ];
    }

    #[DataProvider('texts')]
    public function testTextIsShownOnItsBackground(Text $text, string $shown, string $backgrounds): void
    {
        $screen = Screen::of($text->toVdt(new Terminal()));

        self::assertSame($shown, rtrim($screen->line(self::ROW)));
        self::assertSame($backgrounds, self::backgrounds($screen, self::ROW));
        // The row after it is left as it is
        self::assertSame('', trim($screen->line(self::ROW + 1)));
    }

    /**
     * The space before and after the text are in its size: two columns in double width, on
     * both rows in double height
     *
     * @return iterable<string, array{Size, int, int}> the size, then the column of the space
     *                                                 before it and of the space after it
     */
    public static function sizes(): iterable
    {
        yield 'double width' => [Size::DoubleWidth, 8, 18];
        yield 'double height' => [Size::DoubleHeight, 9, 14];
        yield 'double size' => [Size::Double, 8, 18];
    }

    #[DataProvider('sizes')]
    public function testSpacesAroundTheTextAreInItsSize(Size $size, int $before, int $after): void
    {
        $screen = Screen::of(self::text('Sans', col: 10, size: $size)->toVdt(new Terminal()));

        foreach ([$before, $after] as $col) {
            $space = $screen->cell(self::ROW, $col) ?? self::fail("Nothing at column {$col}");
            self::assertSame(' ', $space->bytes);
            self::assertSame($size->width(), $space->attributes->width());
            self::assertSame(
                $size === Size::DoubleHeight || $size === Size::Double,
                $space->attributes->isDoubleHeight(),
            );
        }
    }

    /**
     * Over a row already drawn: the colour stops at the space after the text, or, filled,
     * runs to the end of the row
     *
     * @return iterable<string, array{bool, string, string}>
     */
    public static function rowsDrawnOver(): iterable
    {
        yield 'not filled' => [
            false,
            'XXXXXXXX Sans X',
            '........RRRRR..',
        ];
        yield 'filled' => [
            true,
            'XXXXXXXX Sans  ',
            '........RRRRRRR',
        ];
    }

    #[DataProvider('rowsDrawnOver')]
    public function testColorOfATextDrawnOverARowStaysOnIt(bool $fill, string $shown, string $backgrounds): void
    {
        $screen = Screen::of(Vdt::join(
            Vdt::position(new Position(self::ROW)),
            Vdt::text(str_repeat('X', times: 15)),
            self::text('Sans', col: 10, fill: $fill)->toVdt(new Terminal()),
        ));

        self::assertSame($shown, substr($screen->line(self::ROW), offset: 0, length: 15));
        self::assertSame($backgrounds, substr(self::backgrounds($screen, self::ROW), offset: 0, length: 15));
    }

    /**
     * Ending in the last column, the cursor is on the next row: nothing is written after the
     * text, filled or not, so as not to clear or colour the next row
     *
     * @return iterable<string, array{bool}>
     */
    public static function fills(): iterable
    {
        yield 'not filled' => [false];
        yield 'filled' => [true];
    }

    #[DataProvider('fills')]
    public function testTextUpToTheLastColumnLeavesTheNextRow(bool $fill): void
    {
        $screen = Screen::of(Vdt::join(
            Vdt::position(new Position(self::ROW + 1)),
            Vdt::text('Next'),
            self::text('Fin40', col: 36, fill: $fill)->toVdt(new Terminal()),
        ));

        self::assertSame(str_repeat(' ', times: 35) . 'Fin40', $screen->line(self::ROW));
        self::assertSame('Next', rtrim($screen->line(self::ROW + 1)));
        self::assertSame(Color::Black, $screen->cell(self::ROW + 1, col: 1)?->attributes->background());
    }

    private static function text(
        string $text,
        int $col = 1,
        bool $centered = false,
        ?Size $size = null,
        bool $fill = false,
    ): Text {
        return new Text($text, new Position(self::ROW, $col), $centered, Color::White, Color::Red, $size, $fill);
    }

    /**
     * The background of each cell of a row, up to its last drawn cell: R red, . black,
     * ' ' empty
     */
    private static function backgrounds(Screen $screen, int $row): string
    {
        $cells = '';
        for ($col = 1; $col <= ScreenSize::COLS; $col++) {
            $cells .= match ($screen->cell($row, $col)?->attributes->background()) {
                null => ' ',
                Color::Red => 'R',
                Color::Black => '.',
                default => '?',
            };
        }

        return rtrim($cells);
    }
}
