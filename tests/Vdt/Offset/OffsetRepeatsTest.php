<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt\Offset;

use MiniPaviService\Tests\Support\MovedScreens;
use MiniPaviService\Vdt\Offset;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Repetitions (REP) moved by Offset
 */
final class OffsetRepeatsTest extends TestCase
{
    /**
     * A character repeated: REP once it is shorter, up to the end of its row (a row is
     * shorter than the most repetitions a REP gives, see
     * VdtTest::testRepeatsACharacterAsManyTimesAsAsked())
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
        MovedScreens::assertScreenMoved(new Vdt($drawn), $x, $y);
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
        MovedScreens::assertScreenMoved(new Vdt($drawn), $x, $y);
    }
}
