<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt\Screen;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Tests\Support\ScreenRows;
use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Screen;
use MiniPaviService\Vdt\Vdt;
use MiniPaviService\Vdt\Videotex;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The zone attributes (background, masking, underline) a delimiter starts, and the rows
 * they are on
 */
final class ZonesTest extends TestCase
{
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

        self::assertSame($expected, ScreenRows::cells(
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

        self::assertSame($expected, ScreenRows::cells(
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

        self::assertSame($backgrounds, ScreenRows::cells(
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
        self::assertSame($shown, ScreenRows::cells(
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
        // At normal size, CAN's delimiter is a cell high (a tall one is two:
        // ClearingTest::testTallClearLineStartsItsZoneOnTheRowAboveToo())
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
        self::assertSame($expected, ScreenRows::zones(Screen::of(new Vdt($sent)), $row, \strlen($expected)));
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

        self::assertSame($expected, ScreenRows::zones($screen, row: 0, length: \strlen($expected)));
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
}
