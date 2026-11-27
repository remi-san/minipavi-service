<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt\Offset;

use MiniPaviService\Tests\Support\MovedScreens;
use MiniPaviService\Vdt\Offset;
use MiniPaviService\Vdt\Screen\Screen;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Clear lines (CAN) and clear screens moved by Offset
 */
final class OffsetClearLinesTest extends TestCase
{
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
        MovedScreens::assertScreenMoved(new Vdt($sent), $x, $y);
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
        MovedScreens::assertScreenMoved(new Vdt($drawn), $x, $y);
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
        MovedScreens::assertScreenMoved(new Vdt($drawn), $x, $y);
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
}
