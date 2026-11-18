<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

use MiniPaviService\Vdt\Screen\Attributes;

/**
 * Videotex codes, by name: control codes, attributes (ESC and the byte after it, which
 * Screen\Attributes names) and sequences. Most are MiniPaviCli's VDT_* constants (CodesTest
 * checks them); HOME, ESC, SEP, SS3 and the mask codes have none. Some are sent as they are;
 * ESC, POSITION, REPEAT, G2, SEP and SS3 start a sequence, with more bytes after them.
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
    // SI and SO: the text (G0) and mosaic (G1) character sets
    public const TEXT_SET = "\x0F";
    public const MOSAIC_SET = "\x0E";
    // ESC: starts an attribute (ESC <byte>) or a protocol sequence
    public const ESC = "\x1B";
    // SS2: starts a G2 character (e.g. £, or an accent and its letter)
    public const G2 = "\x19";
    // SUB: shown as an error character
    public const SUB = "\x1A";
    // SEP and SS3: each followed by one byte
    public const SEP = "\x13";
    public const SS3 = "\x1D";
    // CAN: clears the row from the cursor to its end, with the current background color
    public const CLEAR_LINE = "\x18";
    public const CURSOR_OFF = "\x14";
    // Background and foreground colors swapped
    public const INVERSE_ON = self::ESC . Attributes::INVERSE;
    public const INVERSE_OFF = self::ESC . Attributes::NO_INVERSE;
    public const UNDERLINE_ON = self::ESC . Attributes::UNDERLINE;
    public const UNDERLINE_OFF = self::ESC . Attributes::NO_UNDERLINE;
    public const BLINK_ON = self::ESC . Attributes::BLINK;
    public const BLINK_OFF = self::ESC . Attributes::FIXED;
    // Masked: hidden until revealed
    public const MASK_ON = self::ESC . Attributes::MASK;
    public const MASK_OFF = self::ESC . Attributes::UNMASK;
    public const SIZE_NORMAL = self::ESC . Attributes::SIZE_NORMAL;
    public const SIZE_DOUBLE_HEIGHT = self::ESC . Attributes::DOUBLE_HEIGHT;
    public const SIZE_DOUBLE_WIDTH = self::ESC . Attributes::DOUBLE_WIDTH;
    public const SIZE_DOUBLE = self::ESC . Attributes::DOUBLE;
    // Back to the standard character sets G0 and G1, after redefined characters (DRCS)
    public const RESET_CHARSETS = "\x1B\x28\x40\x1B\x29\x63";
}
