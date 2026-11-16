<?php

namespace MiniPaviService\Vdt;

/**
 * What a Token of Videotex content does
 */
enum TokenType
{
    // A character taking one cell (two in double width), G2 ones included: Token::$bytes
    case Glyph;
    // REP: repeats the last character Token::$count times
    case Repeat;
    // ESC <byte> setting an attribute: Token::$group, and the byte in Token::$bytes
    case Attribute;
    // SO (mosaic, G1) or SI (text, G0): Token::$bytes
    case Charset;
    // US: absolute positioning at Token::$row, Token::$col
    case Position;
    // Relative cursor move: BS, HT, LF, VT or CR in Token::$bytes
    case Move;
    // RS: row 1, column 1
    case Home;
    // FF: clear screen, and home
    case Clear;
    // CAN: clear to the end of the row
    case ClearLine;
    // Anything else, sent as is (protocol, cursor on/off, bell...)
    case Raw;
}
