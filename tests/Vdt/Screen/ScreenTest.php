<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt\Screen;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\Span;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Page\Display\Line;
use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Screen;
use MiniPaviService\Vdt\Vdt;
use MiniPaviService\Vdt\Videotex;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ScreenTest extends TestCase
{
    public function testPreviewShowsTextMosaicAndG2Characters(): void
    {
        // Row 1: text, then "e" with an accent (G2), then mosaic cells: with pixels, empty
        // on black, empty on blue. Row 2: cleared with a blue background from column 3.
        $vdt = "\x1F\x41\x41Hi\x19\x42e\x0E\x21\x20\x1B\x54\x20\x1F\x42\x43\x1B\x54\x18";

        $lines = explode("\n", Screen::of(new Vdt($vdt))->preview());

        self::assertCount(26, $lines);
        self::assertSame(' 0|' . str_repeat(' ', times: 40) . '|', $lines[0]);
        // G2 decoded, as line() does
        self::assertSame(' 1|Hié# #' . str_repeat(' ', times: 34) . '|', $lines[1]);
        self::assertSame(' 2|  ' . str_repeat(' ', times: 38) . '|', $lines[2]);
        self::assertSame('', $lines[25]);
    }

    public function testClearScreenEmptiesEveryRowButTheStatusRow(): void
    {
        $screen = Screen::of(new Vdt("\x1F\x40\x41S\x1F\x45\x45X\x1F\x58\x41Y\x0C"));

        self::assertSame('S', $screen->cell(row: 0, col: 1)?->bytes);
        self::assertNull($screen->cell(row: 5, col: 5));
        self::assertNull($screen->cell(row: 24, col: 1));
    }

    public function testLineGivesTheTextOfARowWithItsAccents(): void
    {
        $screen = Screen::of(Vdt::join(
            Vdt::position(new Position(row: 3)),
            Vdt::text('Dernière page, ça coûte 5£ ½ Œuvre'),
            Vdt::line0('Accès refusé'),
        ));

        $text = 'Dernière page, ça coûte 5£ ½ Œuvre';
        self::assertSame($text . str_repeat(' ', max(0, 40 - mb_strlen($text))), $screen->line(3));
        self::assertStringStartsWith('Accès refusé', ltrim($screen->line(0)));
        self::assertSame(str_repeat(' ', times: 40), $screen->line(4));
        self::assertSame('', $screen->line(99));
    }

    public function testLineShowsMosaicAsInThePreview(): void
    {
        // A mosaic cell with pixels, one empty on black, one empty on blue
        $screen = Screen::of(new Vdt("\x1F\x41\x41\x0E\x21\x20\x1B\x54\x20"));

        self::assertSame('# #', substr($screen->line(1), offset: 0, length: 3));
    }

    public function testTextGivesEveryRow(): void
    {
        $text = Screen::of(Vdt::position(new Position(row: 1))->append(Vdt::text('Hello')))->text();

        $lines = explode("\n", $text);
        self::assertCount(25, $lines);
        self::assertSame(str_pad('Hello', length: 40), $lines[1]);
    }

    public function testDrawsWhatTheContentPositionsAndRepeats(): void
    {
        // Clear screen, position at row 1 column 2, AB, B three more times, red mosaic,
        // clear to the end of the row, back one column, home
        $screen = Screen::of(new Vdt("\x0C\x1F\x41\x42AB\x12\x43\x1B\x41\x0E\x18\x08\x1E"));

        self::assertSame(' ABBBB', rtrim($screen->line(1)));
        self::assertTrue($screen->cell(row: 1, col: 7)?->attributes->isMosaic());
        self::assertSame(Color::Red, $screen->cell(row: 1, col: 40)?->attributes->foreground());
    }

    /**
     * A positioning in digits: US, then 0, 1 or 2 and a digit, the row; the cursor goes to its
     * column 1 (MiniPavi's emulator, locate())
     *
     * @return iterable<string, array{string, int, string}> what is sent after a clear screen,
     *                                                      the row, and the start of its text
     */
    public static function positionsInDigits(): iterable
    {
        yield 'row 5, then text' => ["\x1F05Hello", 5, 'Hello'];
        yield 'row 7, then digits' => ["\x1F0712A", 7, '12A'];
        yield 'row 12' => ["\x1F1200X", 12, '00X'];
        yield 'row 24' => ["\x1F24A", 24, 'A'];
        yield 'the status row' => ["\x1F00S", 0, 'S'];
    }

    #[DataProvider('positionsInDigits')]
    public function testPositionsCanBeGivenInDigits(string $sent, int $row, string $text): void
    {
        $screen = Screen::of(new Vdt("\x0C{$sent}"));

        self::assertSame($text, rtrim($screen->line($row)));
        self::assertSame('', trim($screen->line($row === 1 ? 2 : 1)));
    }

    /**
     * A positioning (US) outside the screen, which the Minitel ignores (MiniPavi's emulator,
     * locate(): neither the cursor nor the attributes change)
     *
     * @return iterable<string, array{string}>
     */
    public static function positionsOutsideTheScreen(): iterable
    {
        yield 'column 0' => ["\x1F\x4A\x40"];
        yield 'column 41' => ["\x1F\x4A\x69"];
        yield 'row 25' => ["\x1F\x59\x41"];
        yield 'row -1' => ["\x1F\x3F\x41"];
        // In digits (positionsInDigits()): 2 and 5, 1 and A (10 + 17), and 3, which isn't
        // one of them, so it is the row of a positioning in bytes (0x33 - 0x40)
        yield 'row 25 in digits' => ["\x1F25"];
        yield 'row 27 in digits' => ["\x1F1A"];
        yield 'a row 3 in digits' => ["\x1F30"];
    }

    #[DataProvider('positionsOutsideTheScreen')]
    public function testPositioningOutsideTheScreenIsIgnored(string $position): void
    {
        // Row 5: red A, B, the positioning, then C
        $screen = Screen::of(new Vdt("\x1F\x45\x41\x1B\x41AB{$position}C"));

        self::assertSame('ABC', rtrim($screen->line(5)));
        self::assertSame(Color::Red, $screen->cell(row: 5, col: 3)?->attributes->foreground());
        self::assertSame(25, \count($screen->cells));
    }

    /**
     * What the Minitel shows (checked in MiniPavi's emulator, #90): an accent (SS2, then
     * grave 0x41, acute 0x42, circumflex 0x43, diaeresis 0x48 or cedilla 0x4B) takes the
     * byte after it, whatever it is, in one cell: the accented letter when the Minitel has
     * it, _ otherwise; any other G2 code it doesn't have is _ too
     *
     * @return iterable<string, array{string, non-empty-string}> content (after positioning at
     *                                                           row 1, column 1), and the
     *                                                           start of row 1
     */
    public static function g2Accents(): iterable
    {
        yield 'acute' => ["\x19\x42e!", 'é!'];
        yield 'grave' => ["\x19\x41e!", 'è!'];
        // A letter the Minitel doesn't have with that accent
        yield 'acute a' => ["\x19\x42a!", '_!'];
        yield 'acute u' => ["\x19\x42u!", '_!'];
        yield 'grave o' => ["\x19\x41o!", '_!'];
        yield 'circumflex I' => ["\x19\x43I!", '_!'];
        yield 'diaeresis u' => ["\x19\x48u!", '_!'];
        yield 'diaeresis a' => ["\x19\x48a!", '_!'];
        yield 'diaeresis o' => ["\x19\x48o!", '_!'];
        // 0x4F, 0x40 and 0x50 aren't accents: a G2 character of their own, then the letter
        yield 'the last code before the letters' => ["\x19\x4Fe!", '_e!'];
        yield 'a G2 code before the accents' => ["\x19\x40e!", '_e!'];
        yield 'a G2 code after the accents' => ["\x19\x50e!", '_e!'];
        yield 'ß' => ["\x19\x7Be!", '_e!'];
        yield '§' => ["\x19\x27e!", '_e!'];
        // A space or a control byte is the accent's byte too: the control byte doesn't act
        // (US, then 0x41 0x44 shown as text)
        yield 'followed by a space' => ["\x19\x42 !", '_!'];
        yield 'followed by a control byte' => ["\x19\x42\x1F\x41\x44e!", '_ADe!'];
        yield 'SS2 followed by a control byte' => ["\x19\x1F\x41\x44e!", '_ADe!'];
        yield 'with its letter at the end of the content' => ["\x19\x42e", 'é '];
        // Cut at the end of the content: nothing drawn yet. The Minitel waits for the rest,
        // which it takes from what it is sent next (e.g. the next page's first byte)
        yield 'an accent at the end of the content' => ["A\x19\x42", 'A '];
        yield 'SS2 at the end of the content' => ["A\x19", 'A '];
    }

    /**
     * @param non-empty-string $shown
     */
    #[DataProvider('g2Accents')]
    public function testAG2AccentGoesWithTheCharacterAfterIt(string $sent, string $shown): void
    {
        self::assertStringStartsWith($shown, Screen::of(new Vdt("\x1F\x41\x41{$sent}"))->line(1));
    }

    /**
     * Every G2 character the Minitel has (MiniPavi's emulator, #90)
     *
     * @return iterable<string, array{string, string}> its bytes after SS2, and its text
     */
    public static function g2Characters(): iterable
    {
        // A list: "\x30" (0) as an array key would be an integer
        $characters = [
            ["\x23", '£'],
            ["\x24", '$'],
            ["\x26", '#'],
            ["\x2C", '←'],
            ["\x2D", '↑'],
            ["\x2E", '→'],
            ["\x2F", '↓'],
            ["\x30", '°'],
            ["\x31", '±'],
            ["\x38", '÷'],
            ["\x3C", '¼'],
            ["\x3D", '½'],
            ["\x3E", '¾'],
            ["\x6A", 'Œ'],
            ["\x7A", 'œ'],
            ["\x41A", 'À'],
            ["\x41a", 'à'],
            ["\x41E", 'È'],
            ["\x41e", 'è'],
            ["\x41u", 'ù'],
            ["\x42E", 'É'],
            ["\x42e", 'é'],
            ["\x43A", 'Â'],
            ["\x43a", 'â'],
            ["\x43E", 'Ê'],
            ["\x43e", 'ê'],
            ["\x43u", 'û'],
            ["\x43i", 'î'],
            ["\x43o", 'ô'],
            ["\x48E", 'Ë'],
            ["\x48e", 'ë'],
            ["\x48i", 'ï'],
            ["\x4BC", 'Ç'],
            ["\x4Bc", 'ç'],
        ];
        foreach ($characters as [$bytes, $text]) {
            yield $text => [$bytes, $text];
        }
    }

    #[DataProvider('g2Characters')]
    public function testShowsTheG2CharactersOfTheMinitel(string $bytes, string $text): void
    {
        self::assertSame("{$text}!", rtrim(Screen::of(new Vdt("\x1F\x41\x41\x19{$bytes}!"))->line(1)));
    }

    /**
     * SYN (0x16) starts a G2 character as SS2 (0x19) does (MiniPavi's emulator, #139)
     *
     * @return iterable<string, array{string, string}> content (after positioning at row 1,
     *                                                 column 1), and row 1 shown
     */
    public static function g2AfterSyn(): iterable
    {
        yield 'an accent' => ["\x16\x42e!", 'é!'];
        yield 'another accent' => ["\x16\x43u!", 'û!'];
        yield 'a character of its own' => ["\x16\x23!", '£!'];
        yield 'a code the Minitel does not have' => ["\x16Z!", '_!'];
        // The second SYN is the G2 code, which the Minitel doesn't have: the accent's code
        // and its letter are then text
        yield 'followed by SYN' => ["\x16\x16\x42e!", '_Be!'];
        yield 'followed by SS2' => ["\x16\x19\x42e!", '_Be!'];
        yield 'SS2 followed by SYN' => ["\x19\x16\x42e!", '_Be!'];
        // Cut at the end of the content: nothing drawn yet
        yield 'at the end of the content' => ["A\x16", 'A'];
    }

    #[DataProvider('g2AfterSyn')]
    public function testSynStartsAG2CharacterAsSs2Does(string $sent, string $shown): void
    {
        self::assertSame($shown, rtrim(Screen::of(new Vdt("\x1F\x41\x41{$sent}"))->line(1)));
    }

    /**
     * The G0 characters the Minitel draws otherwise than in ASCII (MiniPavi's emulator, its
     * sprite sheet font/ef9345-g0.png, #143), and the closest character to what it draws
     *
     * @return iterable<string, array{string, string}> the G0 character, and its text
     */
    public static function g0Characters(): iterable
    {
        yield '0x5E, an arrow up' => ["\x5E", '↑'];
        yield '0x60, a line across the middle' => ["\x60", '─'];
        yield '0x7B, a line down the left edge' => ["\x7B", '▏'];
        yield '0x7D, a line down the right edge' => ["\x7D", '▕'];
        yield '0x7E, a line across the top' => ["\x7E", '▔'];
        yield '0x7F, a full block' => ["\x7F", '█'];
        // As in ASCII
        yield '0x5F, a line across the bottom' => ["\x5F", '_'];
        yield '0x7C, a line down the middle' => ["\x7C", '|'];
    }

    #[DataProvider('g0Characters')]
    public function testShowsTheG0CharactersAsTheMinitelDrawsThem(string $char, string $text): void
    {
        self::assertSame("{$text}!", rtrim(Screen::of(new Vdt("\x1F\x41\x41{$char}!"))->line(1)));
    }

    public function testShowsTheLinesOfAPageAsTheMinitelDrawsThem(): void
    {
        $page = Vdt::join(
            (new Line(new Span(new Position(2, 1), 3), Line::TOP, Color::White))->toVdt(new Terminal()),
            (new Line(new Span(new Position(3, 1), 3), Line::MIDDLE, Color::White))->toVdt(new Terminal()),
            (new Line(new Span(new Position(4, 1), 3), Line::BOTTOM, Color::White))->toVdt(new Terminal()),
        );

        $screen = Screen::of($page);

        self::assertSame(['___', '───', '▔▔▔'], [
            rtrim($screen->line(2)),
            rtrim($screen->line(3)),
            rtrim($screen->line(4)),
        ]);
    }

    public function testG0CharactersInMosaicAreMosaic(): void
    {
        self::assertSame('#####!', rtrim(Screen::of(new Vdt("\x1F\x41\x41\x0E\x5E\x60\x7B\x7E\x7F\x0F!"))->line(1)));
    }

    /**
     * @return iterable<string, array{string}> a sequence none of whose bytes is a character
     */
    public static function sequencesDrawingNothing(): iterable
    {
        yield 'PRO1 (ESC 9 x)' => ["\x1B\x39\x70"];
        yield 'PRO2 (ESC : x y)' => ["\x1B\x3A\x69\x43"];
        yield 'PRO3 (ESC ; x y z)' => ["\x1B\x3B\x60\x58\x52"];
        yield 'whole screen or row attributes (ESC # x y)' => ["\x1B\x23\x20\x54"];
        // Charset designations, G0 to G3 (ESC ( to ESC +), with a space before a DRCS's
        yield 'G0 designation (ESC ( x)' => ["\x1B\x28\x40"];
        yield 'G3 designation (ESC + x)' => ["\x1B\x2B\x40"];
        yield 'DRCS designation as G0 (ESC ( SP x)' => ["\x1B\x28\x20\x42"];
        yield 'DRCS designation as G3 (ESC + SP x)' => ["\x1B\x2B\x20\x42"];
        // Escape sequences of two bytes that don't set an attribute, the first two next to
        // the designations
        yield "ESC '" => ["\x1B\x27"];
        yield 'ESC ,' => ["\x1B\x2C"];
        yield 'cursor position request (ESC a)' => ["\x1B\x61"];
        // CSI, its parameters (bytes 0 to ?), then its final byte
        yield 'erase to the end of the row (CSI K)' => ["\x1B\x5B\x4B"];
        yield 'insert mode off (CSI 4 l)' => ["\x1B\x5B\x34\x6C"];
        yield 'cursor at row 1, column 1 (CSI 01 ; 01 H)' => ["\x1B\x5B\x30\x31\x3B\x30\x31\x48"];
        yield 'a private parameter (CSI ? 4 l)' => ["\x1B\x5B\x3F\x34\x6C"];
        // SEP and SS3, each followed by one byte
        yield 'SEP x' => ["\x13\x49"];
        yield 'SS3 x' => ["\x1D\x49"];
    }

    #[DataProvider('sequencesDrawingNothing')]
    public function testSequencesDrawNothing(string $sequence): void
    {
        // Read whole: none of its bytes drawn, nor the A after it taken as one of them
        self::assertStringStartsWith('A ', Screen::of(new Vdt("\x1F\x41\x41{$sequence}A"))->line(1));
    }

    public function testACsiCutAtTheEndOfTheContentDrawsNothing(): void
    {
        // A, then CSI and a parameter, without its final byte
        self::assertSame('A', trim(Screen::of(new Vdt("\x1F\x41\x41A\x1B\x5B\x34"))->text()));
    }

    /**
     * What the Minitel shows (checked in MiniPavi's emulator, #172): on row 1, ESC M (double
     * height) and ESC O (double size) are discarded, the characters drawn at normal size, one
     * column each; ESC N (double width) is kept
     *
     * @return iterable<string, array{string, string, int, string}>
     */
    public static function sizesOnRow1(): iterable
    {
        yield 'double size' => [Videotex::SIZE_DOUBLE, 'DS end', 1, 'DS end'];
        yield 'double height' => [Videotex::SIZE_DOUBLE_HEIGHT, 'DH1', 1, 'DH1'];
        // A character in two cells
        yield 'double width' => [Videotex::SIZE_DOUBLE_WIDTH, 'W1', 2, 'WW11'];
    }

    #[DataProvider('sizesOnRow1')]
    public function testRow1HasNoDoubleHeight(string $size, string $text, int $width, string $shown): void
    {
        $screen = Screen::of(Vdt::join(Vdt::position(new Position(row: 1)), new Vdt($size), Vdt::text($text)));

        self::assertSame($shown, rtrim($screen->line(1)));
        for ($col = 1; $col <= \strlen($shown); $col++) {
            $attributes = $screen->cell(1, $col)->attributes ?? self::fail("Nothing at row 1, column {$col}");
            self::assertFalse($attributes->isDoubleHeight());
            self::assertSame($width, $attributes->width());
        }
    }

    public function testDoubleHeightDiscardedOnRow1IsntKeptForTheNextRows(): void
    {
        // ESC M, a, six line feeds (LF), b: both at normal size
        $screen = Screen::of(Vdt::join(
            Vdt::position(new Position(row: 1)),
            new Vdt(Videotex::SIZE_DOUBLE_HEIGHT . 'a' . str_repeat("\x0A", times: 6) . 'b'),
        ));

        self::assertFalse($screen->cell(1, col: 1)?->attributes->isDoubleHeight());
        self::assertSame('b', $screen->cell(7, col: 2)?->bytes);
        self::assertFalse($screen->cell(7, col: 2)?->attributes->isDoubleHeight());
    }

    public function testRow2HasDoubleHeight(): void
    {
        // Its top half on row 1
        $screen = Screen::of(Vdt::join(
            Vdt::position(new Position(row: 2, col: 30)),
            new Vdt(Videotex::SIZE_DOUBLE_HEIGHT),
            Vdt::text('T2'),
        ));

        self::assertTrue($screen->cell(2, col: 30)?->attributes->isDoubleHeight());
    }

    /**
     * Every size but the normal one, which the Minitel discards on row 0 (MiniPavi's
     * emulator, decoder.js, setSize(); double width checked by the tester on PR #156)
     *
     * @return iterable<string, array{string}>
     */
    public static function doubleSizes(): iterable
    {
        yield 'double height' => [Videotex::SIZE_DOUBLE_HEIGHT];
        yield 'double width' => [Videotex::SIZE_DOUBLE_WIDTH];
        yield 'double size' => [Videotex::SIZE_DOUBLE];
    }

    #[DataProvider('doubleSizes')]
    public function testSizeIsDiscardedOnRow0(string $size): void
    {
        $screen = Screen::of(new Vdt("\x1F\x40\x41{$size}ZY"));

        $cell = $screen->cell(0, col: 1) ?? self::fail('Nothing on row 0');
        self::assertSame('Z', $cell->bytes);
        self::assertFalse($cell->attributes->isDoubleHeight());
        self::assertFalse($cell->attributes->isDoubleWidth());
        // Normal size: one column
        self::assertSame('ZY', substr($screen->line(0), offset: 0, length: 2));
    }

    /**
     * Double height already set when a cursor move brings the cursor onto row 1: kept, the
     * character drawn tall there, over row 0, with the attributes set since (checked in
     * MiniPavi's emulator, #172)
     *
     * @return iterable<string, array{string, int, bool, Color}>
     */
    public static function sizeBroughtOntoRow1(): iterable
    {
        // Row 24, column 10: ESC M, L, LF to row 1, M
        yield 'a line feed' => ["\x1F\x58\x4A\x1B\x4DL\x0AM", 11, false, Color::White];
        // Row 2, column 20: ESC M, V, VT to row 1, red, W
        yield 'a move up, then a colour' => ["\x1F\x42\x54\x1B\x4DV\x0B\x1B\x41W", 21, false, Color::Red];
        // Row 3, column 30: ESC O, S (two columns), two VT to row 1, T
        yield 'double size' => ["\x1F\x43\x5E\x1B\x4FS\x0B\x0BT", 32, true, Color::White];
    }

    #[DataProvider('sizeBroughtOntoRow1')]
    public function testSizeBroughtOntoRow1IsKept(string $vdt, int $col, bool $doubleWidth, Color $color): void
    {
        $cell = Screen::of(new Vdt($vdt))->cell(1, $col) ?? self::fail("Nothing at row 1, column {$col}");

        self::assertTrue($cell->attributes->isDoubleHeight());
        self::assertSame($doubleWidth, $cell->attributes->isDoubleWidth());
        self::assertSame($color, $cell->attributes->foreground());
    }

    /**
     * A character wrapping at the end of a row in double height or double size: the next row
     * of text is 2 rows down, from 1 to ScreenSize::ROWS and around (checked in MiniPavi's
     * emulator, #7)
     *
     * @return iterable<string, array{int, int, string, list<array{int, int, string}>}>
     */
    public static function tallWraps(): iterable
    {
        // A, B, C on row 10; D and E on row 12, the text carrying on along it
        yield 'mid-screen' => [
            10,
            38,
            Videotex::SIZE_DOUBLE_HEIGHT . 'ABCDE',
            [[10, 40, 'C'], [12, 1, 'D'], [12, 2, 'E']],
        ];
        // R and S in two columns each, on row 13
        yield 'double size' => [13, 37, Videotex::SIZE_DOUBLE . 'RST', [[13, 39, 'S'], [15, 1, 'T']]];
        yield 'from the last row' => [24, 38, Videotex::SIZE_DOUBLE_HEIGHT . 'ABCDE', [[2, 1, 'D'], [2, 2, 'E']]];
        // Drawn tall on row 1, over row 0
        yield 'to row 1' => [23, 39, Videotex::SIZE_DOUBLE_HEIGHT . 'abc', [[23, 40, 'b'], [1, 1, 'c']]];
        // Double height set on row 2, brought onto row 1 by a VT
        yield 'from row 1' => [
            2,
            40,
            Videotex::SIZE_DOUBLE_HEIGHT . Videotex::CURSOR_UP . 'JK',
            [[1, 40, 'J'], [3, 1, 'K']],
        ];
    }

    /**
     * @param string $sent after positioning the cursor at row, col
     * @param list<array{int, int, string}> $drawn row, column and character of tall cells
     */
    #[DataProvider('tallWraps')]
    public function testTallCharacterWrapsTwoRowsDown(int $row, int $col, string $sent, array $drawn): void
    {
        $screen = Screen::of(Vdt::join(Vdt::position(new Position($row, $col)), new Vdt($sent)));

        foreach ($drawn as [$atRow, $atCol, $char]) {
            $cell = $screen->cell($atRow, $atCol) ?? self::fail("Nothing at row {$atRow}, column {$atCol}");
            self::assertSame($char, $cell->bytes, "Row {$atRow}, column {$atCol}");
            self::assertTrue($cell->attributes->isDoubleHeight());
        }
    }

    public function testMosaicCharacterWrapsOneRowDownInDoubleHeight(): void
    {
        // Row 10, column 40: ESC M, SO, then two mosaic characters, which are never tall
        $screen = Screen::of(Vdt::join(
            Vdt::position(new Position(row: 10, col: 40)),
            new Vdt(Videotex::SIZE_DOUBLE_HEIGHT . Videotex::MOSAIC_SET . "\x7F\x21"),
        ));

        self::assertSame("\x21", $screen->cell(11, col: 1)?->bytes);
        self::assertNull($screen->cell(12, col: 1));
    }

    public function testTextAfterSoAndSiWrapsOneRowDown(): void
    {
        // Row 10, column 39: ESC M, A, SO, a mosaic character (column 40), which wraps to row
        // 11, SI, then text: SO and SI set the size back to normal, as the Minitel does, so F
        // wraps to row 12 (checked in MiniPavi's emulator, #26)
        $screen = Screen::of(Vdt::join(
            Vdt::position(new Position(row: 10, col: 39)),
            new Vdt(
                Videotex::SIZE_DOUBLE_HEIGHT
                . 'A'
                . Videotex::MOSAIC_SET
                . "\x7F"
                . Videotex::TEXT_SET
                . str_repeat('C', times: 40)
                . 'F',
            ),
        ));

        $c = $screen->cell(11, col: 1) ?? self::fail('Nothing at row 11, column 1');
        self::assertFalse($c->attributes->isDoubleHeight());
        self::assertSame('F', $screen->cell(12, col: 1)?->bytes);
        self::assertNull($screen->cell(13, col: 1));
    }

    public function testSizeSentInMosaicIsIgnored(): void
    {
        // Row 10, column 40: SO, ESC M, SI, then AB: the Minitel ignores the size in mosaic,
        // so B is at normal size, 1 row down (checked in MiniPavi's emulator)
        $screen = Screen::of(Vdt::join(
            Vdt::position(new Position(row: 10, col: 40)),
            new Vdt(Videotex::MOSAIC_SET . Videotex::SIZE_DOUBLE_HEIGHT . Videotex::TEXT_SET . 'AB'),
        ));

        $b = $screen->cell(11, col: 1) ?? self::fail('Nothing at row 11, column 1');
        self::assertSame('B', $b->bytes);
        self::assertFalse($b->attributes->isDoubleHeight());
        self::assertNull($screen->cell(12, col: 1));
    }

    public function testMoveRightWrapsOneRowDownInDoubleHeight(): void
    {
        // Row 20, column 39: ESC M, O, HT from column 40 to row 21 (not 22), Q
        $screen = Screen::of(Vdt::join(
            Vdt::position(new Position(row: 20, col: 39)),
            new Vdt(Videotex::SIZE_DOUBLE_HEIGHT . 'O' . Videotex::CURSOR_RIGHT . 'Q'),
        ));

        self::assertSame('Q', $screen->cell(21, col: 1)?->bytes);
        self::assertNull($screen->cell(22, col: 1));
    }

    /**
     * Inverse video and underline (separation, in mosaic) after SO and SI, which the Minitel
     * turns off: inverse and separation on SO and SI; an underlined zone ends at a mosaic cell
     * (MiniPavi's emulator, setCharType(), #26)
     *
     * @return iterable<string, array{string, int, bool, bool}>
     */
    public static function charsetResets(): iterable
    {
        $so = Videotex::MOSAIC_SET;
        $si = Videotex::TEXT_SET;
        yield 'inverse after SO and SI' => [Videotex::INVERSE_ON . "A{$so}\x7F{$si}B", 3, false, false];
        yield 'inverse after SI alone' => [Videotex::INVERSE_ON . "A{$si}B", 2, false, false];
        yield 'underline after SO and SI' => [Videotex::UNDERLINE_ON . " C{$so}\x7F{$si}D", 4, false, false];
        yield 'underline kept by SI alone' => [Videotex::UNDERLINE_ON . " C{$si}D", 3, false, true];
        yield 'separation after SO' => ["{$so}" . Videotex::UNDERLINE_ON . "\x7F{$so}\x7F", 2, false, false];
        yield 'separation after SI, then SO' => [
            "{$so}" . Videotex::UNDERLINE_ON . "\x7F{$si}{$so}\x7F",
            2,
            false,
            false,
        ];
        // Set again after SI: kept
        yield 'inverse set after SI' => [Videotex::INVERSE_ON . "A{$si}" . Videotex::INVERSE_ON . 'B', 2, true, false];
    }

    /**
     * @param string $sent after positioning the cursor at row 10, column 1
     */
    #[DataProvider('charsetResets')]
    public function testSoAndSiTurnOffInverseAndUnderline(string $sent, int $col, bool $inverse, bool $underlined): void
    {
        $screen = Screen::of(Vdt::join(Vdt::position(new Position(row: 10)), new Vdt($sent)));

        $cell = $screen->cell(10, $col) ?? self::fail("Nothing at row 10, column {$col}");
        self::assertSame($inverse, $cell->attributes->isInverse(), "Inverse at column {$col}");
        self::assertSame($underlined, $cell->attributes->isUnderlined(), "Underlined at column {$col}");
    }

    /**
     * Underline in text, a zone attribute: ESC Z and ESC Y wait for a delimiter, a space drawn
     * while an attribute waits, which isn't underlined itself and starts a zone running along
     * its row up to the next delimiter, mosaic or empty cell (MiniPavi's emulator,
     * setUnderline(), printDelimiter(), drawRow(), #28)
     *
     * @return iterable<string, array{string, string}> what is sent, and each cell from
     *                                                 column 1: U underlined, . not, ' ' empty
     */
    public static function underlineZones(): iterable
    {
        $on = Videotex::UNDERLINE_ON;
        $off = Videotex::UNDERLINE_OFF;
        $so = Videotex::MOSAIC_SET;
        $si = Videotex::TEXT_SET;
        $blue = Videotex::backgroundColor(Color::Blue);
        $mask = Videotex::MASK_ON;
        // Row 10, column 3, and column 1
        $col3 = "\x1F\x4A\x43";
        $col1 = "\x1F\x4A\x41";

        yield 'nothing waiting' => [' AB', '...'];
        yield 'waiting for a delimiter' => ["{$on}AB", '..'];
        yield 'from a delimiter' => ["{$on} AB", '.UU'];
        yield 'ESC Y waiting too' => ["{$on} AB{$off}CD", '.UUUU'];
        yield 'up to a delimiter' => ["{$on} A{$off} B", '.U..'];
        yield 'spaces in the zone' => ["{$on} A B", '.UUU'];
        // Both its columns a delimiter
        yield 'a double-width delimiter' => [Videotex::SIZE_DOUBLE_WIDTH . "{$on} A", '..UU'];
        yield 'up to a mosaic cell' => ["{$on} A{$so}\x7F{$si}B", '.U..'];
        yield 'up to an empty cell' => ["{$on} A" . Videotex::CURSOR_RIGHT . 'B', '.U .'];
        yield 'waiting after SO' => ["{$on}{$so}{$si} B", '.U'];
        yield 'not waiting after a positioning' => ["{$on}\x1F\x4A\x41 B", '..'];
        yield 'ESC Z in mosaic not waiting' => ["{$so}{$on}{$si} B", '..'];
        yield 'a background delimiting the same zone' => ["{$on} A{$blue} B", '.U.U'];
        yield 'masking delimiting the same zone' => ["{$on} A{$mask} B", '.U.U'];
        yield 'unmasking delimiting the same zone' => ["{$on} A" . Videotex::MASK_OFF . ' B', '.U.U'];
        // Nor does any other attribute
        yield 'inverse not waiting' => ["{$on} A" . Videotex::INVERSE_ON . ' B', '.UUU'];
        yield 'a positioning ending the zone a background delimits' => ["{$on} A{$col3}{$blue} B", '.U..'];
        // SO and SI draw nothing: B is in the zone, and the space after it
        yield 'a background in mosaic not waiting' => ["{$on} A{$so}{$blue}{$si}B C", '.UUUU'];
        // The space a delimiter, of a zone SO ended
        yield 'masking waiting in mosaic' => ["{$on} A{$so}{$mask}{$si}B C", '.UU..'];
        yield 'SO ending the zone a background delimits' => ["{$on} A{$so}{$si}{$blue} B", '.U..'];
        // The background no longer waits: the space is in the zone
        yield 'SO applying the waiting background' => ["{$on} A{$blue}{$so}{$si} B", '.UUU'];
        yield 'SI keeping the background waiting' => ["{$on} A{$blue}{$si} B", '.U.U'];
        // The emulator only makes a text space a delimiter (print()): the mosaic space (column
        // 1) doesn't use up what waits, the text one (column 3) does
        yield 'a mosaic space not delimiting' => ["{$on}{$so}{$mask} \x7F{$si} A", '...U'];
        yield 'over what is already drawn' => ["ABCD{$col3}{$on} ", '...U'];
        yield 'a delimiter drawn over' => ["{$on} AB{$col1}X", '...'];
        $can = Videotex::CLEAR_LINE;
        $ht = Videotex::CURSOR_RIGHT;
        yield 'CAN drawing a delimiter' => ["A{$on}{$can}{$ht}BC", '..UUU'];
        yield 'CAN using up what waits' => ["{$on}{$can}{$ht}A B", '.UUU'];
        // The delimiter isn't underlined, the cleared spaces after it are
        yield 'CAN delimiting the same zone' => ["{$on} A{$on}{$can}", '.U.U'];
        // The cleared spaces are text in the zone, the mosaic cells they replace gone
        yield 'CAN over mosaic cells' => ["{$so}\x7F\x7F{$si}{$col1}{$on}{$can}{$ht}{$ht} A", '.UUU'];
        // Back on row 10, column 4: the cells before are empty, so the space is in no zone
        yield 'a clear screen ending the zone' => ["{$on} AB\x0C\x1F\x4A\x44 B", '   ..'];
        yield 'with a background' => ["{$blue}{$on} A", '.U'];
    }

    /**
     * @param string $sent after positioning the cursor at row 10, column 1
     */
    #[DataProvider('underlineZones')]
    public function testUnderlineIsAZoneStartedByADelimiter(string $sent, string $expected): void
    {
        $screen = Screen::of(Vdt::join(Vdt::position(new Position(row: 10)), new Vdt($sent)));

        $cells = '';
        for ($col = 1; $col <= \strlen($expected); $col++) {
            $cells .= match ($screen->cell(10, $col)?->attributes->isUnderlined()) {
                null => ' ',
                true => 'U',
                false => '.',
            };
        }
        self::assertSame($expected, $cells);
    }

    /**
     * The background colour in text, a zone attribute: ESC P to ESC W wait for a delimiter
     * there, and a text cell is drawn on the background of the last delimiter or mosaic cell
     * before it on its row, black from column 1 and after an empty cell. In mosaic, the colour
     * applies at once, and SO applies the waiting one; a delimiter with no background waiting
     * takes the last one applied (MiniPavi's emulator, setBgColor(), setCharType(),
     * printDelimiter(), drawRow(), #30: each case checked on its screen)
     *
     * @return iterable<string, array{string, string}> what is sent, and the background of
     *                                                 each cell from column 1: B blue, R red,
     *                                                 G green, . black, ' ' empty
     */
    public static function backgroundZones(): iterable
    {
        $so = Videotex::MOSAIC_SET;
        $si = Videotex::TEXT_SET;
        $can = Videotex::CLEAR_LINE;
        $blue = Videotex::backgroundColor(Color::Blue);
        $red = Videotex::backgroundColor(Color::Red);
        $green = Videotex::backgroundColor(Color::Green);
        $underline = Videotex::UNDERLINE_ON;
        // Row 10, column 1
        $col1 = "\x1F\x4A\x41";

        yield 'background waiting for a delimiter' => ["{$blue}AB", '..'];
        yield 'background from a delimiter' => ["{$blue} AB", 'BBB'];
        yield 'background over what is already drawn' => ["ABC{$col1}{$blue} ", 'BBB'];
        yield 'background waiting over text' => ["{$blue} AB{$green}CD E", 'BBBBBGG'];
        yield 'background up to a mosaic cell' => ["{$blue} A{$so}{$red}\x21{$si}B", 'BBRR'];
        // The mosaic cell on the background the delimiter applied
        yield 'background of the zone on a mosaic cell' => ["{$blue} A{$so}\x21{$si}B", 'BBBB'];
        yield 'background text after a mosaic cell' => ["{$so}{$red}\x21{$si}AB", 'RRR'];
        yield 'background up to an empty cell' => ["{$blue} A" . Videotex::CURSOR_RIGHT . 'B', 'BB .'];
        yield 'background SO applying the waiting background' => ["{$blue}A{$so}\x21", '.B'];
        yield 'background not waiting after SO' => ["{$blue}{$so}{$si} A", '..'];
        yield 'background not waiting after a positioning' => ["{$blue}{$col1} A", '..'];
        yield 'background of a double-width delimiter' => [Videotex::SIZE_DOUBLE_WIDTH . "{$blue} A", 'BBBB'];
        // Set in mosaic, applied at once: the next delimiter takes it, not the text before it
        yield 'background set in mosaic, taken by a delimiter' => ["{$so}{$red}{$si}{$underline} A", 'RR'];
        yield 'background set in mosaic, not shown on text' => ["{$so}{$blue}{$si}A{$underline} C", '.BB'];
        // CAN draws spaces, its first a delimiter while something waits (clear())
        yield 'background CAN colouring the row' => ["{$blue}{$can}", 'BBBB'];
        yield 'background CAN in a zone' => ["{$blue} AB{$can}", 'BBBB'];
        yield 'background CAN after text' => ["{$blue}Hello{$can}", '.....BB'];
        // A overwrites CAN's delimiter, so nothing starts a zone
        yield 'background CAN delimiter drawn over' => ["{$blue}{$can}AB", '....'];
    }

    /**
     * @param string $sent after positioning the cursor at row 10, column 1
     */
    #[DataProvider('backgroundZones')]
    public function testBackgroundIsAZoneStartedByADelimiter(string $sent, string $expected): void
    {
        $screen = Screen::of(Vdt::join(Vdt::position(new Position(row: 10)), new Vdt($sent)));

        self::assertSame($expected, self::cellsOfRow(
            $screen,
            row: 10,
            length: \strlen($expected),
            shown: static fn(Attributes $a): string => match ($a->background()) {
                Color::Blue => 'B',
                Color::Red => 'R',
                Color::Green => 'G',
                Color::Black => '.',
                default => '?',
            },
        ));
    }

    /**
     * Masking, a zone attribute in text and in mosaic: ESC X and ESC _ wait for a delimiter,
     * and masking runs from it to the next delimiter that sets it, across mosaic and empty
     * cells, the delimiters themselves masked as what is before them (MiniPavi's emulator,
     * setMask(), printDelimiter(), drawRow(), #30: each case checked on its screen)
     *
     * @return iterable<string, array{string, string}> what is sent, and each cell from
     *                                                 column 1: M masked, . not, ' ' empty
     */
    public static function maskZones(): iterable
    {
        $so = Videotex::MOSAIC_SET;
        $si = Videotex::TEXT_SET;
        $mask = Videotex::MASK_ON;

        yield 'masking waiting for a delimiter' => ["{$mask}AB", '..'];
        yield 'masking across a mosaic cell' => ["{$mask} A{$so}\x21{$si}B", '.MMM'];
        yield 'masking up to a delimiter unmasking' => ["{$mask} A" . Videotex::MASK_OFF . ' B', '.MM.'];
        yield 'masking kept by a delimiter of another attribute' => [
            "{$mask} A" . Videotex::backgroundColor(Color::Blue) . ' B',
            '.MMM',
        ];
        yield 'masking with a background' => [Videotex::backgroundColor(Color::Blue) . "{$mask} A", '.M'];
        yield 'masking across an empty cell' => ["{$mask} A" . Videotex::CURSOR_RIGHT . 'B', '.M M'];
        // Waiting in mosaic, for a text space
        yield 'masking waiting in mosaic for a text space' => ["{$mask}{$so}\x21{$si} A{$so}\x21", '..MM'];
    }

    /**
     * @param string $sent after positioning the cursor at row 10, column 1
     */
    #[DataProvider('maskZones')]
    public function testMaskingIsAZoneStartedByADelimiter(string $sent, string $expected): void
    {
        $screen = Screen::of(Vdt::join(Vdt::position(new Position(row: 10)), new Vdt($sent)));

        self::assertSame($expected, self::cellsOfRow(
            $screen,
            row: 10,
            length: \strlen($expected),
            shown: static fn(Attributes $a): string => $a->isMasked() ? 'M' : '.',
        ));
    }

    /**
     * A delimiter after a mosaic cell: SI keeps the background colour the mosaic cell was
     * drawn on, which the delimiter takes, if no other waits, and it starts the zone of what
     * waits, underline included, as after a text cell (MiniPavi's emulator, setCharType(),
     * printDelimiter(), drawRow(), #95: each case checked on its screen, zoomed, the text
     * underlined in its own colour)
     *
     * @return iterable<string, array{string, string, string}> what is sent, and each cell from
     *                                                         column 1: its background (B
     *                                                         blue, R red, . black), and
     *                                                         whether it is masked (M),
     *                                                         underlined (U) or neither (.)
     */
    public static function zonesAfterAMosaicCell(): iterable
    {
        $so = Videotex::MOSAIC_SET;
        $si = Videotex::TEXT_SET;
        $underline = Videotex::UNDERLINE_ON;
        $red = Videotex::backgroundColor(Color::Red);
        $blue = Videotex::backgroundColor(Color::Blue);
        $mask = Videotex::MASK_ON;

        yield 'underline after a mosaic cell' => ["{$so}{$red}\x7F{$si}{$underline} A", 'RRR', '..U'];
        yield 'underline after a mosaic cell, along the zone' => [
            "{$so}{$red}\x7F{$si}{$underline} AAAA",
            'RRRRRR',
            '..UUUU',
        ];
        yield 'underline with no mosaic cell' => ["{$underline} AAAA", '.....', '.UUUU'];
        yield 'underline after a red background set in mosaic, no mosaic cell' => [
            "{$so}{$red}{$si}{$underline} AAAA",
            'RRRRR',
            '.UUUU',
        ];
        yield 'underline after a red mosaic cell on black' => [
            "{$so}" . Videotex::textColor(Color::Red) . "\x7F{$si}{$underline} AAAA",
            '......',
            '..UUUU',
        ];
        yield 'background waiting after a mosaic cell' => ["{$so}{$red}\x7F{$si}{$blue} AAAA", 'RBBBBB', '......'];
        yield 'masking waiting after a mosaic cell' => ["{$so}{$red}\x7F{$si}{$mask} AAAA", 'RRRRRR', '..MMMM'];
        yield 'background and underline waiting after a mosaic cell' => [
            "{$so}{$red}\x7F{$si}{$blue}{$underline} AAAA",
            'RBBBBB',
            '..UUUU',
        ];
        yield 'underline after a text cell after a mosaic cell' => [
            "{$so}{$red}\x7F{$si}B{$underline} AAAA",
            'RRRRRRR',
            '...UUUU',
        ];
    }

    /**
     * @param string $sent after positioning the cursor at row 10, column 1
     */
    #[DataProvider('zonesAfterAMosaicCell')]
    public function testDelimiterAfterAMosaicCellStartsItsZone(string $sent, string $backgrounds, string $shown): void
    {
        $screen = Screen::of(Vdt::join(Vdt::position(new Position(row: 10)), new Vdt($sent)));

        self::assertSame($backgrounds, self::cellsOfRow(
            $screen,
            row: 10,
            length: \strlen($backgrounds),
            shown: static fn(Attributes $a): string => match ($a->background()) {
                Color::Blue => 'B',
                Color::Red => 'R',
                Color::Black => '.',
                default => '?',
            },
        ));
        self::assertSame($shown, self::cellsOfRow(
            $screen,
            row: 10,
            length: \strlen($shown),
            shown: self::maskedOrUnderlined(...),
        ));
    }

    /**
     * M if a cell with these attributes is masked, U if it is underlined, . if neither
     */
    private static function maskedOrUnderlined(Attributes $attributes): string
    {
        if ($attributes->isMasked()) {
            return 'M';
        }

        return $attributes->isUnderlined() ? 'U' : '.';
    }

    public function testZonesOfBackgroundAndMaskingEndWithTheirRow(): void
    {
        // Row 10, column 39: blue, masking, a space, A, then B wrapped to row 11
        $screen = Screen::of(Vdt::join(
            Vdt::position(new Position(row: 10, col: 39)),
            new Vdt(Videotex::backgroundColor(Color::Blue) . Videotex::MASK_ON . ' AB'),
        ));

        $a = $screen->cell(10, col: 40) ?? self::fail('Nothing at row 10, column 40');
        self::assertSame(Color::Blue, $a->attributes->background());
        self::assertTrue($a->attributes->isMasked());
        $b = $screen->cell(11, col: 1) ?? self::fail('Nothing at row 11, column 1');
        self::assertSame(Color::Black, $b->attributes->background());
        self::assertFalse($b->attributes->isMasked());
    }

    /**
     * A tall delimiter (ESC M, ESC O) is a delimiter on the row above too, where its top half
     * is: it starts its zone there, for the cells after it on that row. Tall text is text
     * there, with or without a tall delimiter, carrying the zone of that row on or ending the
     * one of a delimiter it is drawn over (MiniPavi's emulator, printDelimiter(),
     * printG0Char(), drawRow(), #94: each case checked on its screen). Screen keeps a tall
     * character in its bottom cell only: the cells of the row above it are what was drawn
     * there before
     *
     * @return iterable<string, array{string, int, string}> what is sent, the row looked at,
     *                                                      and each of its cells from column 1:
     *                                                      M masked, U underlined, or its
     *                                                      background: B blue, R red, G green,
     *                                                      . black; ' ' empty
     */
    public static function zonesOnTheRowAbove(): iterable
    {
        $tall = Videotex::SIZE_DOUBLE_HEIGHT;
        $blue = Videotex::backgroundColor(Color::Blue);
        $red = Videotex::backgroundColor(Color::Red);
        $green = Videotex::backgroundColor(Color::Green);
        $mask = Videotex::MASK_ON;
        // Row 9, then row 10, at column n
        $row9 = static fn(int $col): string => "\x1F\x49" . \chr(0x40 + $col);
        $row10 = static fn(int $col): string => "\x1F\x4A" . \chr(0x40 + $col);

        // The issue's two cases (seen by the tester on PR #91)
        yield 'a background' => ["{$row9(5)}XYZ{$row10(4)}{$tall}{$blue} ", 9, '   BBBB'];
        yield 'masking' => ["{$row9(5)}MNO{$row10(4)}{$mask}{$tall} ", 9, '   .MMM'];
        yield 'underline' => ["{$row9(5)}UND{$row10(4)}{$tall}" . Videotex::UNDERLINE_ON . ' ', 9, '   .UUU'];
        // Empty cells end the background, not the masking
        yield 'a background up to empty cells' => [
            "{$row9(5)}AB{$row9(10)}CD{$row10(4)}{$tall}{$blue} ",
            9,
            '   BBB   ..',
        ];
        yield 'masking across empty cells' => ["{$row9(5)}EF{$row9(10)}GH{$row10(4)}{$tall}{$mask} ", 9, '   .MM   MM'];
        // Columns 4 and 5 of rows 9 and 10: P, drawn over by its top half, is in the zone
        yield 'a double-size delimiter' => [
            "{$row9(5)}PQRSTU{$row10(4)}" . Videotex::SIZE_DOUBLE . "{$blue} ",
            9,
            '   BBBBBBB',
        ];
        // Q, R and S are text on row 9 too: the zone carries on over them to c, d and e
        yield 'tall text after the delimiter' => ["{$row9(7)}cde{$row10(3)}{$tall}{$blue} QRS", 9, '  BBBBBBB'];
        // A space written on the row above afterwards is in the zone, as V after it
        yield 'a space written after it' => [
            "{$row9(5)}U{$row10(4)}{$tall}" . Videotex::UNDERLINE_ON . " {$row9(6)} V",
            9,
            '   .UUU',
        ];
        // Tall text, with no tall delimiter, is text on the row above too: x's top half
        // (column 5) carries the zone of row 9 on, over an empty cell, a mosaic cell (on green)
        // and an underlined zone's gap, and ends the zone of the delimiter it draws over
        yield 'tall text over an empty cell' => ["{$row9(2)}{$blue} AB{$row9(6)}CD{$row10(5)}{$tall}x", 9, ' BBBBBB'];
        yield 'tall text over a mosaic cell' => [
            "{$row9(2)}{$red} AB"
                . Videotex::MOSAIC_SET
                . "{$green}\x7F"
                . Videotex::TEXT_SET
                . "CD{$row10(5)}{$tall}x",
            9,
            ' RRRRRR',
        ];
        yield 'tall text over an underlined gap' => [
            "{$row9(2)}" . Videotex::UNDERLINE_ON . " AB{$row9(6)}CD{$row10(5)}{$tall}x",
            9,
            ' .UUUUU',
        ];
        // The delimiter's space, in column 4, is shown out of a zone too
        yield 'tall text over a delimiter' => ["{$row9(2)}ab{$blue} cd{$row10(4)}{$tall}x", 9, ' .....'];
        // In a zone, the one before the delimiter carries on (checked by the tester on PR #144)
        yield 'tall text over a delimiter in a zone' => [
            "{$row9(1)}{$red} ab{$green} cdef{$row10(4)}{$tall}x",
            9,
            'RRRRRRRR',
        ];
        // c, drawn over by its top half, is in the zone
        yield 'a zone already on the row above' => [
            "{$row9(2)}{$red} abcdefgh{$row10(5)}{$tall}{$blue} ",
            9,
            ' RRRBBBBBB',
        ];
        // Its masking is what the cursor's zone leaves: none, after a positioning. The
        // delimiter is masked as what is before it
        yield 'unmasking a masked zone above' => [
            "{$row9(2)}{$mask} abcdefgh{$row10(5)}{$tall}{$blue} ",
            9,
            ' .MMMBBBBB',
        ];
        yield 'up to a delimiter on the row above' => [
            "{$row9(5)}ijkl{$green} nop{$row10(4)}{$tall}{$blue} ",
            9,
            '   BBBBBGGGG',
        ];
        yield 'up to the end of the row above' => [
            "{$row9(30)}ABCDEFGHIJK{$row10(29)}{$tall}{$blue} ",
            9,
            str_repeat(' ', times: 28) . str_repeat('B', times: 12),
        ];
        // Its top half is on row 8: row 10 is below it
        yield 'not below' => ["{$row10(5)}XYZ{$row9(4)}{$tall}{$blue} ", 10, '    ...'];
        // At normal size, CAN's delimiter is a cell high (a tall one is two: testTallClearLineStartsItsZoneOnTheRowAboveToo())
        yield 'not above a CAN delimiter' => ["{$row9(5)}XYZ{$row10(4)}{$blue}" . Videotex::CLEAR_LINE, 9, '    ...'];
        // Its own row, as before
        yield 'the delimiter row' => [
            "{$row10(4)}{$tall}{$blue} tall" . Videotex::SIZE_NORMAL . 'zz',
            10,
            '   BBBBBBB',
        ];
    }

    #[DataProvider('zonesOnTheRowAbove')]
    public function testTallCharacterIsOnTheRowAboveToo(string $sent, int $row, string $expected): void
    {
        self::assertSame($expected, self::zonesOfRow(Screen::of(new Vdt($sent)), $row, \strlen($expected)));
    }

    /**
     * The row above row 1 is the status row (checked in MiniPavi's emulator, #94): a tall
     * delimiter on row 1 starts its zone there too; ESC M sent on row 1 is discarded, so a
     * delimiter drawn after it is a cell high
     *
     * @return iterable<string, array{string, string}> what is sent after the status row and
     *                                                 row 1 are written, and each cell of the
     *                                                 status row (as zonesOnTheRowAbove())
     */
    public static function zonesOnTheStatusRow(): iterable
    {
        $tall = Videotex::SIZE_DOUBLE_HEIGHT;
        $blue = Videotex::backgroundColor(Color::Blue);

        yield 'not above row 1' => ["\x1F\x41\x44{$tall}{$blue} ", str_repeat('.', times: 20)];
        // Row 24, column 10: ESC M, L, a line feed to row 1, which keeps double height
        yield 'brought onto row 1' => [
            "\x1F\x58\x4A{$tall}L\x0A{$blue} ",
            str_repeat('.', times: 10) . str_repeat('B', times: 10),
        ];
    }

    #[DataProvider('zonesOnTheStatusRow')]
    public function testTallDelimiterOnRow1StartsItsZoneOnTheStatusRow(string $sent, string $expected): void
    {
        $screen = Screen::of(new Vdt("\x1F\x40\x41STATUS ROW TEXT 0123\x1F\x41\x41ROW ONE TEXT 0123456{$sent}"));

        self::assertSame($expected, self::zonesOfRow($screen, row: 0, length: \strlen($expected)));
    }

    /**
     * Each cell of row, from column 1 to length, as zonesOnTheRowAbove() gives them
     */
    private static function zonesOfRow(Screen $screen, int $row, int $length): string
    {
        return self::cellsOfRow($screen, $row, $length, static fn(Attributes $a): string => match (true) {
            $a->isMasked() => 'M',
            $a->isUnderlined() => 'U',
            default => match ($a->background()) {
                Color::Blue => 'B',
                Color::Red => 'R',
                Color::Green => 'G',
                Color::Black => '.',
                default => '?',
            },
        });
    }

    /**
     * Each cell of row, from column 1 to length, as shown: ' ' if empty
     *
     * @param \Closure(Attributes): string $shown
     */
    private static function cellsOfRow(Screen $screen, int $row, int $length, \Closure $shown): string
    {
        $cells = '';
        for ($col = 1; $col <= $length; $col++) {
            $cell = $screen->cell($row, $col);
            $cells .= $cell === null ? ' ' : $shown($cell->attributes);
        }

        return $cells;
    }

    public function testTallCharacterShowsItsTopHalfOnTheRowAbove(): void
    {
        // Rows 2 and 3 full of x and y, then ESC M and AB from row 3, column 1: their top
        // halves are drawn over row 2's x, as in MiniPavi's emulator (#141)
        $screen = Screen::of(
            new Vdt("\x1F\x42\x41x\x12\x67\x1F\x43\x41y\x12\x67\x1F\x43\x41" . Videotex::SIZE_DOUBLE_HEIGHT . 'AB'),
        );

        self::assertSame('AB' . str_repeat('x', times: 38), $screen->line(2));
        self::assertSame('AB' . str_repeat('y', times: 38), $screen->line(3));
        $top = $screen->cell(2, col: 1) ?? self::fail('Nothing at row 2, column 1');
        self::assertTrue($top->attributes->isDoubleHeight());
        self::assertFalse($top->part->isBottom());
        self::assertTrue($screen->cell(3, col: 1)?->part->isBottom());
    }

    public function testTopHalfIsOnTheZoneOfTheRowAbove(): void
    {
        // Row 3: x, then a zone on blue to the end of the row; ESC M and ABC from row 4,
        // column 5: their top halves are on blue, their bottom halves on black (the emulator)
        $screen = Screen::of(new Vdt(
            "\x1F\x43\x41x"
            . Videotex::backgroundColor(Color::Blue)
            . ' '
            . Videotex::CLEAR_LINE
            . "\x1F\x44\x45"
            . Videotex::SIZE_DOUBLE_HEIGHT
            . 'ABC',
        ));

        self::assertSame('A', $screen->cell(3, col: 5)?->bytes);
        self::assertSame(Color::Blue, $screen->cell(3, col: 5)?->attributes->background());
        self::assertSame(Color::Black, $screen->cell(4, col: 5)?->attributes->background());
    }

    public function testDoubleSizeCharacterShowsAQuarterInEachOfItsCells(): void
    {
        $screen = Screen::of(new Vdt("\x1F\x46\x41" . Videotex::SIZE_DOUBLE . 'W'));

        // Each cell: right, bottom (1 if so)
        $parts = [];
        foreach ([[5, 1], [5, 2], [6, 1], [6, 2]] as [$row, $col]) {
            $cell = $screen->cell($row, $col) ?? self::fail("Nothing at row {$row}, column {$col}");
            self::assertSame('W', $cell->bytes);
            $parts[] = ($cell->part->isRight() ? '1' : '0') . ($cell->part->isBottom() ? '1' : '0');
        }
        self::assertSame(['00', '10', '01', '11'], $parts);
        self::assertNull($screen->cell(5, col: 3));
    }

    public function testDoubleSizeCharacterInTheLastColumnShowsItsLeftQuarters(): void
    {
        // Row 6, column 40: ESC O and W, its top left quarter on row 5, its bottom left one
        // on row 6, as in MiniPavi's emulator (#141); Z wraps two rows down
        $screen = Screen::of(new Vdt("\x1F\x46\x68" . Videotex::SIZE_DOUBLE . 'WZ'));

        $top = $screen->cell(5, col: 40) ?? self::fail('Nothing at row 5, column 40');
        self::assertSame('W', $top->bytes);
        self::assertFalse($top->part->isRight());
        self::assertFalse($top->part->isBottom());
        self::assertTrue($screen->cell(6, col: 40)?->part->isBottom());
        self::assertSame('ZZ', substr($screen->line(8), offset: 0, length: 2));
    }

    /**
     * A tall delimiter shows its top half on the row above, on its zone's background (#151,
     * checked in MiniPavi's emulator by the tester on PR #150), even once a tall character
     * from the row below draws over its bottom half; a double-size one in both of its columns
     * there, as the emulator writes it in each of its cells (decoder.js, printDelimiter())
     *
     * @return iterable<string, array{string, string, array{?Color, ?Color}}> the size and
     *                                                                       the space drawn on
     *                                                                       row 10, column 2,
     *                                                                       what is drawn
     *                                                                       after, and the
     *                                                                       backgrounds of
     *                                                                       row 9, columns 2
     *                                                                       and 3
     */
    public static function tallDelimiters(): iterable
    {
        $blue = Videotex::backgroundColor(Color::Blue) . ' ';
        $tallX = "\x1F\x4B\x42" . Videotex::SIZE_DOUBLE_HEIGHT . 'X';

        yield 'a delimiter' => [Videotex::SIZE_DOUBLE_HEIGHT . $blue, '', [Color::Blue, null]];
        yield 'a space' => [Videotex::SIZE_DOUBLE_HEIGHT . ' ', '', [Color::Black, null]];
        yield 'a delimiter, drawn over from the row below' => [
            Videotex::SIZE_DOUBLE_HEIGHT . $blue,
            $tallX,
            [Color::Blue, null],
        ];
        yield 'a space, drawn over from the row below' => [
            Videotex::SIZE_DOUBLE_HEIGHT . ' ',
            $tallX,
            [Color::Black, null],
        ];
        yield 'a double-size delimiter' => [Videotex::SIZE_DOUBLE . $blue, '', [Color::Blue, Color::Blue]];
        yield 'a double-size space' => [Videotex::SIZE_DOUBLE . ' ', '', [Color::Black, Color::Black]];
    }

    /**
     * @param array{?Color, ?Color} $backgrounds
     */
    #[DataProvider('tallDelimiters')]
    public function testTallDelimiterShowsItsTopHalfOnTheRowAbove(
        string $space,
        string $after,
        array $backgrounds,
    ): void {
        $screen = Screen::of(new Vdt("\x1F\x4A\x42{$space}" . Videotex::SIZE_NORMAL . "   {$after}"));

        self::assertSame($backgrounds, [
            $screen->cell(9, col: 2)?->attributes->background(),
            $screen->cell(9, col: 3)?->attributes->background(),
        ]);
    }

    public function testClearLineClearsUpToTheLastColumn(): void
    {
        // Row 10: a mosaic character on red in column 40, then from column 1, a blue
        // background and CAN, its first space a delimiter
        $screen = Screen::of(new Vdt("\x1F\x4A\x68\x0E\x1B\x51\x7F\x1F\x4A\x41\x1B\x54\x18"));

        $last = $screen->cell(10, col: 40) ?? self::fail('Nothing at row 10, column 40');
        self::assertSame(' ', $last->bytes);
        self::assertFalse($last->attributes->isMosaic());
        self::assertSame(Color::Blue, $last->attributes->background());
    }

    /**
     * CAN prints a space at the cursor, in its attributes, for each column from the cursor's
     * to the last, as MiniPavi's emulator does (decoder.js, clear("eol"), checked there in
     * #141): in double height, they clear the row above too; in double width, they run on
     * over the next row, two rows down in double size, as text wraps
     *
     * @return iterable<string, array{string, array<int, string>}> what is sent after rows 1 to
     *                                                              24 are filled (with a to x,
     *                                                              by row), and the text
     *                                                              of rows
     */
    public static function clearedLines(): iterable
    {
        $tall = Videotex::SIZE_DOUBLE_HEIGHT;
        $wide = Videotex::SIZE_DOUBLE_WIDTH;
        /** @param non-negative-int $count */
        $spaces = static fn(int $count): string => str_repeat(' ', $count);
        /** @param non-negative-int $count */
        $row = static fn(string $char, int $count = 40): string => str_repeat($char, $count);

        yield 'double height' => [
            "\x1F\x43\x41{$tall}ABC" . Videotex::CLEAR_LINE,
            [
                1 => $row('a'),
                2 => 'ABC' . $spaces(37),
                3 => 'ABC' . $spaces(37),
                4 => $row('d'),
            ],
        ];
        yield 'double size' => [
            "\x1F\x51\x41" . Videotex::SIZE_DOUBLE . 'AB' . Videotex::CLEAR_LINE,
            [
                15 => $row('o'),
                16 => 'AABB' . $spaces(36),
                17 => 'AABB' . $spaces(36),
                18 => $spaces(36) . $row('r', count: 4),
                19 => $spaces(36) . $row('s', count: 4),
                20 => $row('t'),
            ],
        ];
        yield 'double width' => [
            "\x1F\x42\x41{$wide}AB" . Videotex::CLEAR_LINE,
            [
                2 => 'AABB' . $spaces(36),
                3 => $spaces(36) . $row('c', count: 4),
                4 => $row('d'),
            ],
        ];
        // From column 2, the 20th space shows its left half in column 40, then 19 more
        yield 'double width from an even column' => [
            "\x1F\x47\x42{$wide}" . Videotex::CLEAR_LINE,
            [
                7 => 'g' . $spaces(39),
                8 => $spaces(38) . $row('h', count: 2),
            ],
        ];
        yield 'double width in the last column' => [
            "\x1F\x56\x68{$wide}" . Videotex::CLEAR_LINE . 'Z',
            [
                22 => $row('v', count: 39) . 'Z',
                23 => $row('w'),
            ],
        ];
        // Green waiting: the first space a delimiter, then 37 more, up to column 38 of row 3
        yield 'double width, its first space a delimiter' => [
            "\x1F\x42\x43{$wide}\x1B\x52" . Videotex::CLEAR_LINE,
            [
                2 => 'bb' . $spaces(38),
                3 => $spaces(38) . $row('c', count: 2),
            ],
        ];
        // From row 24 to row 1, as text wraps
        yield 'double width on the last row' => [
            "\x1F\x58\x45{$wide}" . Videotex::CLEAR_LINE,
            [
                24 => $row('x', count: 4) . $spaces(36),
                1 => $spaces(36) . $row('a', count: 4),
                2 => $row('b'),
            ],
        ];
    }

    /**
     * @param array<int, string> $lines
     */
    #[DataProvider('clearedLines')]
    public function testClearLinePrintsASpaceForEachColumnLeft(string $sent, array $lines): void
    {
        $filled = '';
        for ($row = 1; $row <= 24; $row++) {
            $filled .= "\x1F" . chr(0x40 + $row) . "\x41" . chr(0x60 + $row) . Videotex::REPEAT . chr(0x40 + 39);
        }

        $screen = Screen::of(new Vdt($filled . $sent));

        foreach ($lines as $row => $line) {
            self::assertSame($line, $screen->line($row), "Row {$row}");
        }
    }

    public function testClearLineOnTheStatusRowIsAtNormalSize(): void
    {
        // Row 0, column 30: ESC N, discarded there, underline waiting, CAN: a delimiter in
        // column 30, then spaces a column wide in the underlined zone, as in MiniPavi's
        // emulator (checked by the tester on PR #156)
        $screen = Screen::of(
            new Vdt("\x1F\x40\x5E" . Videotex::SIZE_DOUBLE_WIDTH . Videotex::UNDERLINE_ON . Videotex::CLEAR_LINE),
        );

        self::assertFalse($screen->cell(0, col: 30)?->attributes->isUnderlined());
        self::assertTrue($screen->cell(0, col: 31)?->attributes->isUnderlined());
        self::assertTrue($screen->cell(0, col: 40)?->attributes->isUnderlined());
        self::assertSame(1, $screen->cell(0, col: 31)?->attributes->width());
    }

    public function testTallClearLineStartsItsZoneOnTheRowAboveToo(): void
    {
        // Row 8: x, then a zone on blue to the end of the row; from row 9, column 5: ESC M, a
        // red background waiting, ABC and CAN, its first space a delimiter: on row 8 too, the
        // rest of the row is red (checked in MiniPavi's emulator, #141)
        $screen = Screen::of(new Vdt(
            "\x1F\x48\x41x"
            . Videotex::backgroundColor(Color::Blue)
            . ' '
            . Videotex::CLEAR_LINE
            . "\x1F\x49\x45"
            . Videotex::SIZE_DOUBLE_HEIGHT
            . Videotex::backgroundColor(Color::Red)
            . 'ABC'
            . Videotex::CLEAR_LINE,
        ));

        self::assertSame('.BBBBBBRRRRR', self::zonesOfRow($screen, row: 8, length: 12));
        self::assertSame('    ...RRRRR', self::zonesOfRow($screen, row: 9, length: 12));
        self::assertSame(Color::Red, $screen->cell(8, col: 40)?->attributes->background());
    }

    public function testUnderlinedZoneEndsWithItsRow(): void
    {
        // Row 10, column 38: ESC Z, a space, A, B, then C wrapped to row 11
        $screen = Screen::of(Vdt::join(
            Vdt::position(new Position(row: 10, col: 38)),
            new Vdt(Videotex::UNDERLINE_ON . ' ABC'),
        ));

        self::assertTrue($screen->cell(10, col: 40)?->attributes->isUnderlined());
        $c = $screen->cell(11, col: 1) ?? self::fail('Nothing at row 11, column 1');
        self::assertSame('C', $c->bytes);
        self::assertFalse($c->attributes->isUnderlined());
    }

    public function testClearScreenEndsTheZonesOfEveryRowButTheStatusRow(): void
    {
        // An underlined zone from column 1 on rows 0, 1 and 24, a clear screen, then C and D
        // drawn in columns 3 and 4 of rows 1 and 24, and E after the zone of row 0
        $zone = Videotex::UNDERLINE_ON . ' AB';
        $screen = Screen::of(
            new Vdt(
                "\x1F\x40\x41{$zone}\x1F\x41\x41{$zone}\x1F\x58\x41{$zone}\x0C"
                . "\x1F\x41\x43CD\x1F\x58\x43CD\x1F\x40\x44E",
            ),
        );

        self::assertTrue($screen->cell(0, col: 4)?->attributes->isUnderlined());
        foreach ([1, 24] as $row) {
            foreach ([3, 4] as $col) {
                self::assertFalse($screen->cell($row, $col)?->attributes->isUnderlined(), "row {$row}, column {$col}");
            }
        }
    }

    public function testContentStartsAtRow1Column1WithTheDefaultAttributes(): void
    {
        $cell = Screen::of(new Vdt('A'))->cell(1, col: 1) ?? self::fail('Nothing at row 1, column 1');

        self::assertSame('A', $cell->bytes);
        self::assertEquals(Attributes::defaults(), $cell->attributes);
    }

    /**
     * @return iterable<string, array{string, int, int}> the sequence, and where A is drawn
     *                                                    after it
     */
    public static function positionings(): iterable
    {
        yield 'a positioning' => ["\x1F\x45\x47", 5, 7];
        yield 'a home' => [Videotex::HOME, 1, 1];
        yield 'a clear screen' => [Videotex::CLEAR_SCREEN, 1, 1];
    }

    #[DataProvider('positionings')]
    public function testPositioningSetsTheAttributesBackToTheirDefaults(string $sequence, int $row, int $col): void
    {
        // XY, red, inverse, double width, then the sequence and A
        $screen = Screen::of(
            new Vdt(
                'XY' . Videotex::ESC . "\x41" . Videotex::INVERSE_ON . Videotex::SIZE_DOUBLE_WIDTH . "{$sequence}A",
            ),
        );

        $cell = $screen->cell($row, $col) ?? self::fail("Nothing at row {$row}, column {$col}");
        self::assertSame('A', $cell->bytes);
        self::assertEquals(Attributes::defaults(), $cell->attributes);
    }

    public function testDoubleWidthCharacterInTheLastColumnStaysInIt(): void
    {
        $screen = Screen::of(Vdt::join(
            Vdt::position(new Position(row: 3, col: 40)),
            new Vdt(Videotex::SIZE_DOUBLE_WIDTH . 'A'),
        ));

        self::assertSame('A', $screen->cell(3, col: 40)?->bytes);
        self::assertSame(str_repeat(' ', times: 39) . 'A', $screen->line(3));
    }

    public function testMoveUpFromRow1GoesToTheLastRow(): void
    {
        // As the Minitel does in page mode, the status row skipped (moveCursor())
        $screen = Screen::of(new Vdt("\x1F\x41\x43" . Videotex::CURSOR_UP . 'A'));

        self::assertSame('A', $screen->cell(24, col: 3)?->bytes);
        self::assertSame('', trim($screen->line(0)));
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function movesLeftFromColumn1(): iterable
    {
        yield 'to the row above' => ["\x1F\x43\x41", 2];
        // As moving up, the status row skipped
        yield 'from row 1, to the last row' => ["\x1F\x41\x41", 24];
    }

    #[DataProvider('movesLeftFromColumn1')]
    public function testMoveLeftFromColumn1GoesToTheLastColumnOfTheRowAbove(string $position, int $row): void
    {
        $screen = Screen::of(new Vdt($position . Videotex::CURSOR_LEFT . 'A'));

        self::assertSame('A', $screen->cell($row, col: 40)?->bytes);
        self::assertSame(str_repeat(' ', times: 39) . 'A', $screen->line($row));
    }

    /**
     * MiniPavi's emulator prints the error character (SUB) as 0x7F, as the preview draws it
     * (HtmlRenderer): a full block in text, all six pixels in mosaic
     */
    public function testErrorCharacterIsAFullBlockInTheText(): void
    {
        $screen = Screen::of(new Vdt("\x1F\x41\x41A" . Videotex::SUB . 'B'));

        self::assertSame('A█B', rtrim($screen->line(1)));
    }

    public function testErrorCharacterIsAMosaicCellWithPixelsInMosaic(): void
    {
        $screen = Screen::of(new Vdt("\x1F\x41\x41\x0E" . Videotex::SUB));

        self::assertSame('#', rtrim($screen->line(1)));
    }

    public function testTextOnTheStatusRowStaysInItsLastColumn(): void
    {
        // Row 0, column 39: A, B, then C over B, as the status row doesn't wrap (moveCursor())
        $screen = Screen::of(new Vdt("\x1F\x40\x67ABC"));

        self::assertSame(str_repeat(' ', times: 38) . 'AC', $screen->line(0));
        self::assertSame('', trim($screen->line(1)));
    }

    /**
     * As MiniPavi's emulator does (decoder.js, moveCursor() and locate()): on the status row,
     * VT is ignored and BS stays on it, while LF, and HT from the last column, leave it for
     * where the cursor was when a positioning (US) brought it there
     *
     * @return iterable<string, array{string, int, int, string}> what is sent, and the row,
     *                                                           column and character drawn
     *                                                           last
     */
    public static function movesOnTheStatusRow(): iterable
    {
        // The cases of #98, after a clear screen (the cursor at row 1, column 1)
        yield 'VT, ignored' => ["\x0C\x1F\x40\x4AS\x0BV", 0, 11, 'V'];
        yield 'BS from column 1, to column 40' => ["\x0C\x1F\x40\x41\x08B", 0, 40, 'B'];
        yield 'LF, to the position before row 0' => ["\x0C\x1F\x40\x45L\x0AM", 1, 1, 'M'];
        yield 'HT from column 40, to the position before row 0' => ["\x0C\x1F\x40\x68\x09H", 1, 1, 'H'];
        // Row 10, column 8 after X
        yield 'BS within the row' => ["\x1F\x4A\x47X\x1F\x40\x45\x08B", 0, 4, 'B'];
        yield 'HT within the row, to column 40' => ["\x1F\x4A\x47X\x1F\x40\x67\x09H", 0, 40, 'H'];
        yield 'LF, to row 10' => ["\x1F\x4A\x47X\x1F\x40\x45L\x0AM", 10, 8, 'M'];
        yield 'HT from column 40, to row 10' => ["\x1F\x4A\x47X\x1F\x40\x68\x09H", 10, 8, 'H'];
        yield 'LF after a positioning from the status row, as usual' => [
            "\x1F\x4A\x47X\x1F\x40\x45L\x1F\x43\x43\x0AM",
            4,
            3,
            'M',
        ];
        // Each positioning onto row 0 keeps where the cursor was, even on row 0
        yield 'LF after a positioning on row 0, to the first one' => [
            "\x1F\x4A\x47X\x1F\x40\x45\x1F\x40\x50L\x0AM",
            0,
            5,
            'M',
        ];
        yield 'LF after leaving row 0, as usual' => ["\x1F\x4A\x47X\x1F\x40\x45L\x0A\x0AM", 11, 8, 'M'];
    }

    #[DataProvider('movesOnTheStatusRow')]
    public function testMovesOnTheStatusRowFollowTheMinitel(string $sent, int $row, int $col, string $drawn): void
    {
        $screen = Screen::of(new Vdt($sent));

        self::assertSame($drawn, $screen->cell($row, $col)?->bytes);
        self::assertSame(1, substr_count($screen->text(), $drawn));
    }

    public function testLeavingTheStatusRowGivesBackTheAttributesSetBeforeIt(): void
    {
        // Red and underline waiting at row 10, defaults on row 0, then LF: a space, the
        // delimiter starting the underline, and M in red, underlined
        $screen = Screen::of(
            new Vdt("\x1F\x4A\x41" . Videotex::ESC . "\x41" . Videotex::UNDERLINE_ON . "\x1F\x40\x45L\x0A M"),
        );

        $status = $screen->cell(0, col: 5) ?? self::fail('Nothing at row 0, column 5');
        self::assertEquals(Attributes::defaults(), $status->attributes);
        $m = $screen->cell(10, col: 2) ?? self::fail('Nothing at row 10, column 2');
        self::assertSame('M', $m->bytes);
        self::assertSame(Color::Red, $m->attributes->foreground());
        self::assertTrue($m->attributes->isUnderlined());
    }

    public function testTextWrapsToTheNextRow(): void
    {
        $screen = Screen::of(Vdt::join(Vdt::position(new Position(row: 3, col: 40)), Vdt::text('AB')));

        self::assertSame('A', $screen->cell(3, col: 40)?->bytes);
        self::assertSame('B', $screen->cell(4, col: 1)?->bytes);
    }

    public function testUnderlinedMosaicIsSeparated(): void
    {
        // SO, ESC Z, red, a mosaic character, then one after SO again (no longer separated);
        // SO first, as it turns separation off
        $screen = Screen::of(
            new Vdt(
                Videotex::MOSAIC_SET
                . Videotex::UNDERLINE_ON
                . Videotex::ESC
                . "\x41\x7F"
                . Videotex::MOSAIC_SET
                . "\x7F",
            ),
        );

        $separated = $screen->cell(1, col: 1) ?? self::fail('Nothing at row 1, column 1');
        self::assertTrue($separated->attributes->isMosaic());
        self::assertTrue($separated->attributes->isSeparated());
        self::assertSame(Color::Red, $separated->attributes->foreground());
        self::assertFalse($screen->cell(1, col: 2)?->attributes->isSeparated());
    }

    public function testUnderlinedTextIsntSeparated(): void
    {
        $a = Screen::of(new Vdt(Videotex::UNDERLINE_ON . ' A'))->cell(1, col: 2) ?? self::fail('Nothing at column 2');

        self::assertTrue($a->attributes->isUnderlined());
        self::assertFalse($a->attributes->isSeparated());
    }

    /**
     * As the Minitel does: in mosaic, ESC L, M, N, O and ESC ] are discarded (MiniPavi's
     * emulator, setSize() and setInvert())
     *
     * @return iterable<string, array{string}>
     */
    public static function discardedInMosaic(): iterable
    {
        yield 'double height' => [Videotex::SIZE_DOUBLE_HEIGHT];
        yield 'double width' => [Videotex::SIZE_DOUBLE_WIDTH];
        yield 'double size' => [Videotex::SIZE_DOUBLE];
        yield 'inverse' => [Videotex::INVERSE_ON];
    }

    #[DataProvider('discardedInMosaic')]
    public function testSizesAndInverseAreDiscardedInMosaic(string $sent): void
    {
        $screen = Screen::of(Vdt::join(
            Vdt::position(new Position(row: 10)),
            new Vdt(Videotex::MOSAIC_SET . $sent . "\x7F"),
        ));

        $cell = $screen->cell(10, col: 1) ?? self::fail('Nothing at row 10, column 1');
        self::assertTrue($cell->attributes->isMosaic());
        self::assertFalse($cell->attributes->isDoubleHeight());
        self::assertFalse($cell->attributes->isDoubleWidth());
        self::assertFalse($cell->attributes->isInverse());
    }

    /**
     * In mosaic, the text colour, blinking, the background colour and separation apply at
     * once, to the next mosaic character
     */
    public function testColorsBlinkingAndSeparationApplyAtOnceInMosaic(): void
    {
        $screen = Screen::of(new Vdt(
            Videotex::MOSAIC_SET
            . Videotex::textColor(Color::Red)
            . Videotex::BLINK_ON
            . Videotex::backgroundColor(Color::Blue)
            . Videotex::UNDERLINE_ON
            . "\x7F",
        ));

        $cell = $screen->cell(1, col: 1) ?? self::fail('Nothing at column 1');
        self::assertSame(Color::Red, $cell->attributes->foreground());
        self::assertTrue($cell->attributes->isBlinking());
        self::assertSame(Color::Blue, $cell->attributes->background());
        self::assertTrue($cell->attributes->isSeparated());
    }

    public function testRepeatWithoutACharacterDrawsNothing(): void
    {
        // REP 3 with nothing sent before, then A: drawn where the cursor was
        $screen = Screen::of(new Vdt("\x1F\x41\x41" . Videotex::REPEAT . "\x43A"));

        self::assertSame('A', rtrim($screen->line(1)));
    }
}
