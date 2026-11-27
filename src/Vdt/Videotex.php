<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Vdt\Screen\Attribute\BackgroundColor;
use MiniPaviService\Vdt\Screen\Attribute\Blinking;
use MiniPaviService\Vdt\Screen\Attribute\CharacterSize;
use MiniPaviService\Vdt\Screen\Attribute\Inversion;
use MiniPaviService\Vdt\Screen\Attribute\Masking;
use MiniPaviService\Vdt\Screen\Attribute\TextColor;
use MiniPaviService\Vdt\Screen\Attribute\Underlining;

/**
 * Videotex codes, by name: control codes, attributes (ESC and the byte of a
 * Screen\Attribute\Attribute) and sequences. Most are MiniPaviCli's VDT_* constants (CodesTest
 * checks them); HOME, ESC, SEP, SS3 and the mask codes have none. Some are sent as they are;
 * ESC, POSITION, REPEAT, G2, G2_SYN, SEP and SS3 start a sequence, with more bytes after them:
 * position() and repeat() write POSITION's and REPEAT's. MAX_REPEAT is not a code: the most
 * repetitions one REP gives.
 */
final class Videotex
{
    // Cursor moves: BS, HT, LF, VT and CR
    public const CURSOR_LEFT = "\x08";
    public const CURSOR_RIGHT = "\x09";
    public const CURSOR_DOWN = "\x0A";
    public const CURSOR_UP = "\x0B";
    public const CARRIAGE_RETURN = "\x0D";
    // FF: clears the screen (but row 0), and brings the cursor home
    public const CLEAR_SCREEN = "\x0C";
    // RS: brings the cursor home, row 1, column 1, with the default attributes
    public const HOME = "\x1E";
    // US: positions the cursor, followed by its row and column (0x40 + n each)
    public const POSITION = "\x1F";
    // REP: repeats the last character, followed by the count (0x40 + n)
    public const REPEAT = "\x12";
    // Most repetitions one REP gives: its count, 0x40 + n, stays a 7-bit byte (0x7F)
    public const MAX_REPEAT = 0x3F;
    // SI and SO: the text (G0) and mosaic (G1) character sets
    public const TEXT_SET = "\x0F";
    public const MOSAIC_SET = "\x0E";
    // ESC: starts an attribute (ESC <byte>) or a protocol sequence
    public const ESC = "\x1B";
    // SS2: starts a G2 character (e.g. £, or an accent and its letter)
    public const G2 = "\x19";
    // SYN: starts a G2 character too, as SS2 does (MiniPavi's emulator, #139); never written
    public const G2_SYN = "\x16";
    // SUB: shown as an error character
    public const SUB = "\x1A";
    // SEP and SS3: each followed by one byte
    public const SEP = "\x13";
    public const SS3 = "\x1D";
    // CAN: clears the row from the cursor to its end, with the current background color
    public const CLEAR_LINE = "\x18";
    public const CURSOR_OFF = "\x14";
    // Background and foreground colors swapped
    public const INVERSE_ON = self::ESC . Inversion::Inverse->value;
    public const INVERSE_OFF = self::ESC . Inversion::NoInverse->value;
    public const UNDERLINE_ON = self::ESC . Underlining::Underline->value;
    public const UNDERLINE_OFF = self::ESC . Underlining::NoUnderline->value;
    public const BLINK_ON = self::ESC . Blinking::Blink->value;
    public const BLINK_OFF = self::ESC . Blinking::Fixed->value;
    // Masked: hidden until revealed
    public const MASK_ON = self::ESC . Masking::Mask->value;
    public const MASK_OFF = self::ESC . Masking::Unmask->value;
    public const SIZE_NORMAL = self::ESC . CharacterSize::Normal->value;
    public const SIZE_DOUBLE_HEIGHT = self::ESC . CharacterSize::DoubleHeight->value;
    public const SIZE_DOUBLE_WIDTH = self::ESC . CharacterSize::DoubleWidth->value;
    public const SIZE_DOUBLE = self::ESC . CharacterSize::Double->value;
    // Back to the standard character sets G0 and G1, after redefined characters (DRCS)
    public const RESET_CHARSETS = "\x1B\x28\x40\x1B\x29\x63";

    /**
     * Positions the cursor at row, col: POSITION, then the row and the column, 0x40 + n each
     */
    public static function position(int $row, int $col): string
    {
        return self::POSITION . \chr(0x40 + $row) . \chr(0x40 + $col);
    }

    /**
     * Repeats the last character printed count more times (0 to MAX_REPEAT): REPEAT, then the
     * count, 0x40 + n
     */
    public static function repeat(int $count): string
    {
        return self::REPEAT . \chr(0x40 + $count);
    }

    /**
     * Sets the text (foreground) color: ESC and the byte of its Screen\Attribute\TextColor
     */
    public static function textColor(Color $color): string
    {
        return (new TextColor($color))->code();
    }

    /**
     * Sets the background color: ESC and the byte of its Screen\Attribute\BackgroundColor
     */
    public static function backgroundColor(Color $color): string
    {
        return (new BackgroundColor($color))->code();
    }
}
