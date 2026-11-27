<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt\Screen;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Vdt\Screen\Screen;
use MiniPaviService\Vdt\Vdt;
use MiniPaviService\Vdt\Videotex;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What a repetition (REP) draws
 */
final class RepeatTest extends TestCase
{
    /**
     * REP right after a CAN repeats a space, the last character CAN printed, in the cursor's
     * attributes, from where CAN left the cursor (MiniPavi's emulator, decoder.js: print()
     * remembers the spaces of clear("eol") as the last character drawn, #163)
     *
     * @return iterable<string, array{string, array<int, string>}> what is sent after a clear
     *                                                              screen, and the text of rows
     */
    public static function repeatsAfterClearLine(): iterable
    {
        /** @param non-negative-int $count */
        $back = static fn(int $count): string => str_repeat("\x08", $count);
        $spaces = str_repeat(' ', times: 40);

        // From column 3, where CAN leaves the cursor
        yield 'at normal size' => [
            "\x1F\x42\x41AB" . Videotex::CLEAR_LINE . Videotex::REPEAT . "\x43",
            [2 => 'AB' . str_repeat(' ', times: 38)],
        ];
        yield 'over the text before' => [
            "\x1F\x42\x41AB" . Videotex::CLEAR_LINE . $back(2) . Videotex::REPEAT . "\x42",
            [2 => $spaces],
        ];
        // Two spaces two columns wide over AB, also two columns wide
        yield 'in double width' => [
            "\x1F\x42\x41"
                . Videotex::SIZE_DOUBLE_WIDTH
                . 'AB'
                . Videotex::CLEAR_LINE
                . $back(4)
                . Videotex::REPEAT
                . "\x42",
            [2 => $spaces, 3 => $spaces],
        ];
        // Two spaces in double size over AB, on rows 4 and 5
        yield 'in double size' => [
            "\x1F\x45\x41" . Videotex::SIZE_DOUBLE . 'AB' . Videotex::CLEAR_LINE . $back(4) . Videotex::REPEAT . "\x42",
            [4 => $spaces, 5 => $spaces],
        ];
        // An empty mosaic cell over the full one
        yield 'in mosaic' => [
            "\x1F\x42\x41"
                . Videotex::MOSAIC_SET
                . "\x7F"
                . Videotex::CLEAR_LINE
                . $back(1)
                . Videotex::REPEAT
                . "\x41",
            [2 => $spaces],
        ];
    }

    /**
     * @param array<int, string> $lines
     */
    #[DataProvider('repeatsAfterClearLine')]
    public function testRepeatAfterClearLineRepeatsASpace(string $sent, array $lines): void
    {
        $screen = Screen::of(new Vdt(Videotex::CLEAR_SCREEN . $sent));

        foreach ($lines as $row => $line) {
            self::assertSame($line, $screen->line($row), "Row {$row}");
        }
    }

    /**
     * REP before any character repeats the character printed before the content (the
     * gateway's PIN, the previous page's last character), which can't be known: a space
     * stands in for it, in the cursor's attributes (#163)
     *
     * @return iterable<string, array{string, array<int, string>}> what is sent after a clear
     *                                                              screen, and the text of rows
     */
    public static function repeatsBeforeAnyCharacter(): iterable
    {
        // Three spaces, then X in column 4
        yield 'at normal size' => [
            "\x1F\x42\x41" . Videotex::REPEAT . "\x43X",
            [2 => '   X' . str_repeat(' ', times: 36)],
        ];
        // Two spaces two columns wide, then X in columns 5 and 6
        yield 'in double width' => [
            "\x1F\x42\x41" . Videotex::SIZE_DOUBLE_WIDTH . Videotex::REPEAT . "\x42X",
            [2 => '    XX' . str_repeat(' ', times: 34)],
        ];
    }

    /**
     * @param array<int, string> $lines
     */
    #[DataProvider('repeatsBeforeAnyCharacter')]
    public function testRepeatBeforeAnyCharacterRepeatsASpace(string $sent, array $lines): void
    {
        $screen = Screen::of(new Vdt(Videotex::CLEAR_SCREEN . $sent));

        foreach ($lines as $row => $line) {
            self::assertSame($line, $screen->line($row), "Row {$row}");
        }
    }

    public function testRepeatBeforeAnyCharacterDrawsADelimiterWhileAZoneAttributeWaits(): void
    {
        // Blue background waiting, REP: the first space standing in for the character
        // printed before is a delimiter, which colours the text after it blue, as a space
        // drawn there does
        $screen = Screen::of(
            new Vdt(
                Videotex::CLEAR_SCREEN
                . "\x1F\x42\x41"
                . Videotex::backgroundColor(Color::Blue)
                . Videotex::REPEAT
                . "\x42X",
            ),
        );

        self::assertSame('  X', rtrim($screen->line(2)));
        self::assertSame(Color::Blue, $screen->cell(row: 2, col: 3)?->attributes->background());
    }

    public function testRepeatAfterClearLineDrawsOverItsDelimiter(): void
    {
        // Blue background waiting, CAN: its first space, in column 3, a delimiter colouring
        // the rest of the row blue; REP: three spaces from column 3, the first one over the
        // delimiter, which uses up nothing waiting any more, so the row is black, as when
        // text is written there
        $screen = Screen::of(new Vdt(
            Videotex::CLEAR_SCREEN
            . "\x1F\x42\x41AB"
            . Videotex::backgroundColor(Color::Blue)
            . Videotex::CLEAR_LINE
            . Videotex::REPEAT
            . "\x43",
        ));

        self::assertSame('AB' . str_repeat(' ', times: 38), $screen->line(2));
        self::assertSame(Color::Black, $screen->cell(row: 2, col: 4)?->attributes->background());
        self::assertSame(Color::Black, $screen->cell(row: 2, col: 40)?->attributes->background());
    }
}
