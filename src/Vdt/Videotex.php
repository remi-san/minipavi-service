<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

/**
 * Videotex codes, to send as they are: the framework's names for the VDT_* constants of
 * MiniPaviCli
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
    // CAN: clears the row from the cursor to its end, with the current background color
    public const CLEAR_LINE = "\x18";
    public const CURSOR_OFF = "\x14";
    // Background and foreground colors swapped
    public const INVERSE_ON = "\x1B\x5D";
    public const INVERSE_OFF = "\x1B\x5C";
    public const UNDERLINE_ON = "\x1B\x5A";
    public const UNDERLINE_OFF = "\x1B\x59";
    public const BLINK_ON = "\x1B\x48";
    public const BLINK_OFF = "\x1B\x49";
    // Masked: hidden until revealed
    public const MASK_ON = "\x1B\x58";
    public const MASK_OFF = "\x1B\x5F";
    public const SIZE_NORMAL = "\x1B\x4C";
    public const SIZE_DOUBLE_HEIGHT = "\x1B\x4D";
    public const SIZE_DOUBLE_WIDTH = "\x1B\x4E";
    public const SIZE_DOUBLE = "\x1B\x4F";
    // Back to the standard character sets G0 and G1, after redefined characters (DRCS)
    public const RESET_CHARSETS = "\x1B\x28\x40\x1B\x29\x63";
}
