<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

/**
 * Videotex codes, to send as they are: the framework's names for the VDT_* constants of
 * MiniPaviCli
 */
final class Videotex
{
    // CAN: clears the row from the cursor to its end, with the current background color
    public const CLEAR_LINE = "\x18";
    public const CURSOR_OFF = "\x14";
    // Background and foreground colors swapped
    public const INVERSE_ON = "\x1B\x5D";
    public const INVERSE_OFF = "\x1B\x5C";
    public const UNDERLINE_ON = "\x1B\x5A";
    public const UNDERLINE_OFF = "\x1B\x59";
    public const SIZE_NORMAL = "\x1B\x4C";
    public const SIZE_DOUBLE_HEIGHT = "\x1B\x4D";
    public const SIZE_DOUBLE_WIDTH = "\x1B\x4E";
    public const SIZE_DOUBLE = "\x1B\x4F";
    // Back to the standard character sets G0 and G1, after redefined characters (DRCS)
    public const RESET_CHARSETS = "\x1B\x28\x40\x1B\x29\x63";
}
