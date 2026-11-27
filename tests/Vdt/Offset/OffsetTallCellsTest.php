<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt\Offset;

use MiniPaviService\Tests\Support\MovedScreens;
use MiniPaviService\Vdt\Offset;
use MiniPaviService\Vdt\Screen\Attribute\CharacterSize;
use MiniPaviService\Vdt\Screen\Attribute\Underlining;
use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Cell;
use MiniPaviService\Vdt\Screen\Part;
use MiniPaviService\Vdt\Screen\Screen;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Double-height and double-size cells moved by Offset, and their halves on the rows they
 * are moved to
 */
final class OffsetTallCellsTest extends TestCase
{
    public function testOffsetMovesATallCharacterWrappedTwoRowsDown(): void
    {
        // Row 10, column 39: ESC M, F, G, then H wrapped to row 12 (#7), moved 2 rows down
        $moved = Screen::of((new Offset(x: 0, y: 2))->apply(new Vdt("\x1F\x4A\x67\x1B\x4DFGH")));

        self::assertSame('G', $moved->cell(row: 12, col: 40)?->bytes);
        self::assertSame('H', $moved->cell(row: 14, col: 1)?->bytes);
        self::assertTrue($moved->cell(row: 14, col: 1)?->attributes->isDoubleHeight());
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
        MovedScreens::assertScreenMoved(new Vdt($drawn), $x, y: 0);
    }

    /**
     * A tall character's top half shows no underline (MiniPavi's emulator, font-sprite.js,
     * writeChar(): it is drawn at the bottom of the character): two screens differing only
     * by it show the same, for the screen tests (MovedScreens::asShown(), #168)
     */
    public function testScreensDifferingByATallCharactersTopHalfUnderlineShowTheSame(): void
    {
        $underlined = self::tallA(top: Underlining::Underline, bottom: Underlining::Underline);
        $topNotUnderlined = self::tallA(top: Underlining::NoUnderline, bottom: Underlining::Underline);

        MovedScreens::assertShowsMoved($underlined, $topNotUnderlined, x: 0, y: 0);
    }

    /**
     * A tall character's bottom half shows its underline: two screens differing by it don't
     * show the same, for the screen tests (MovedScreens::asShown())
     */
    public function testScreensDifferingByATallCharactersBottomHalfUnderlineShowOtherwise(): void
    {
        $underlined = self::tallA(top: Underlining::Underline, bottom: Underlining::Underline);
        $bottomNotUnderlined = self::tallA(top: Underlining::Underline, bottom: Underlining::NoUnderline);

        $this->expectException(AssertionFailedError::class);
        MovedScreens::assertShowsMoved($underlined, $bottomNotUnderlined, x: 0, y: 0);
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
        MovedScreens::assertScreenMoved(new Vdt($drawn), $x, $y);
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
}
