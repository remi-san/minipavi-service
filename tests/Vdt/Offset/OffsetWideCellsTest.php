<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt\Offset;

use MiniPaviService\Tests\Support\MovedScreens;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Double-width cells moved by Offset partly off the screen, on the left or the right
 */
final class OffsetWideCellsTest extends TestCase
{
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
        MovedScreens::assertScreenMoved(new Vdt($drawn), $x, y: 0);
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
        MovedScreens::assertScreenMoved(new Vdt($drawn), $x, y: 0);
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
        MovedScreens::assertScreenMoved(new Vdt($drawn), $x, $y);
    }

    #[DataProvider('wideSpacesMovedOffTheLeft')]
    public function testOffsetWritesTheRightHalfOfAWideSpaceAsANormalWidthSpace(string $drawn, int $x): void
    {
        MovedScreens::assertScreenMoved(new Vdt($drawn), $x, y: 0);
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
        // its right half shows isn't compared, see
        // MovedScreens::assertScreenMoved())
        yield 'a double-width character' => ["\x1F\x4A\x68\x1BNX", -2];
    }

    #[DataProvider('wideSpacesCutByTheLastColumn')]
    public function testOffsetWritesTheLeftHalfOfAWideSpaceInTheLastColumnAsANormalWidthSpace(
        string $drawn,
        int $x,
    ): void {
        MovedScreens::assertScreenMoved(new Vdt($drawn), $x, y: 0);
    }
}
