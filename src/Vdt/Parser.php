<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

use MiniPaviService\Vdt\Screen\Attribute\AttributeReader;
use MiniPaviService\Vdt\Screen\G2Characters;
use MiniPaviService\Vdt\Token\Attribute;
use MiniPaviService\Vdt\Token\CarriageReturn;
use MiniPaviService\Vdt\Token\Charset;
use MiniPaviService\Vdt\Token\ClearLine;
use MiniPaviService\Vdt\Token\ClearScreen;
use MiniPaviService\Vdt\Token\CursorDown;
use MiniPaviService\Vdt\Token\CursorLeft;
use MiniPaviService\Vdt\Token\CursorRight;
use MiniPaviService\Vdt\Token\CursorUp;
use MiniPaviService\Vdt\Token\Glyph;
use MiniPaviService\Vdt\Token\Home;
use MiniPaviService\Vdt\Token\Position;
use MiniPaviService\Vdt\Token\Raw;
use MiniPaviService\Vdt\Token\Repeat;
use MiniPaviService\Vdt\Token\Token;

/**
 * Cuts Videotex content into Tokens: how Vdt reads it, tested through what it draws
 *
 * @internal
 */
final class Parser
{
    /**
     * @return list<Token>
     */
    public function parse(string $vdt): array
    {
        $tokens = [];
        $length = strlen($vdt);
        for ($i = 0; $i < $length; $i++) {
            $byte = $vdt[$i];
            if (ord($byte) >= 0x20) {
                $tokens[] = new Glyph($byte);
                continue;
            }
            switch ($byte) {
                case Videotex::G2:
                case Videotex::G2_SYN: // G2 character: its code, and after an accent the byte after it
                    $size = G2Characters::isAccent($vdt[$i + 1] ?? '') ? 3 : 2;
                    $glyph = substr($vdt, $i, $size);
                    // Cut at the end of the content, it draws nothing: the Minitel waits for the
                    // rest, which it would take from what it is sent next
                    $tokens[] = strlen($glyph) === $size ? new Glyph($glyph) : new Raw($glyph);
                    $i += strlen($glyph) - 1;
                    break;
                case Videotex::SUB: // shown as an error character
                    $tokens[] = new Glyph($byte);
                    break;
                case Videotex::REPEAT:
                    $tokens[] = new Repeat(substr($vdt, $i, length: 2), count: ord($vdt[$i + 1] ?? "\x40") - 0x40);
                    $i++;
                    break;
                case Videotex::MOSAIC_SET:
                case Videotex::TEXT_SET:
                    $tokens[] = new Charset($byte);
                    break;
                case Videotex::POSITION: // row, column as 0x40+n, or a row as 2 digits
                    [$position, $skipped] = self::position($vdt, $i);
                    $tokens[] = $position;
                    $i += $skipped;
                    break;
                case Videotex::CURSOR_LEFT:
                    $tokens[] = new CursorLeft();
                    break;
                case Videotex::CURSOR_RIGHT:
                    $tokens[] = new CursorRight();
                    break;
                case Videotex::CURSOR_DOWN:
                    $tokens[] = new CursorDown();
                    break;
                case Videotex::CURSOR_UP:
                    $tokens[] = new CursorUp();
                    break;
                case Videotex::CARRIAGE_RETURN:
                    $tokens[] = new CarriageReturn();
                    break;
                case Videotex::HOME:
                    $tokens[] = new Home();
                    break;
                case Videotex::CLEAR_SCREEN:
                    $tokens[] = new ClearScreen();
                    break;
                case Videotex::CLEAR_LINE:
                    $tokens[] = new ClearLine();
                    break;
                case Videotex::SEP: // one byte follows each
                case Videotex::SS3:
                    $tokens[] = new Raw(substr($vdt, $i, length: 2));
                    $i++;
                    break;
                case Videotex::ESC:
                    $sequence = $this->escapeSequence($vdt, $i);
                    $attribute = strlen($sequence) === 2 ? AttributeReader::read($sequence[1]) : null;
                    $tokens[] = $attribute === null ? new Raw($sequence) : new Attribute($attribute);
                    $i += strlen($sequence) - 1;
                    break;
                default:
                    $tokens[] = new Raw($byte);
            }
        }
        return $tokens;
    }

    /**
     * Escape sequence starting at position start (ESC included)
     */
    private function escapeSequence(string $vdt, int $start): string
    {
        $next = ord($vdt[$start + 1] ?? "\0");
        $length = match (true) {
            $next === 0x39 => 3, // PRO1 x
            // PRO2 x y, or ESC # SP x: whole screen or row attributes
            $next === 0x3A, $next === 0x23 => 4,
            $next === 0x3B => 5, // PRO3 x y z
            $next >= 0x28 && $next <= 0x2B => ($vdt[$start + 2] ?? '') === ' ' ? 4 : 3, // charset designation
            $next === 0x5B => $this->csiLength($vdt, $start), // CSI ... final byte
            default => 2,
        };
        return substr($vdt, $start, $length);
    }

    /**
     * Position (US) at offset i: the token, and the 2 bytes it takes after US. As the Minitel
     * reads them (MiniPavi's emulator, locate()): the row and the column as 0x40 + n, or, when
     * the first is 0, 1 or 2, the row in digits (10 times the first, plus the second), the
     * cursor in column 1. Off the screen, the Player ignores it (Cursor::position())
     *
     * @return array{Token, int}
     */
    private static function position(string $vdt, int $i): array
    {
        $first = ord($vdt[$i + 1] ?? "\x41");
        $second = ord($vdt[$i + 2] ?? "\x41");
        [$row, $col] =
            $first >= 0x30 && $first <= 0x32
                ? [(10 * ($first - 0x30)) + ($second - 0x30), 1]
                : [$first - 0x40, $second - 0x40];

        return [new Position(substr($vdt, $i, length: 3), row: $row, col: $col), 2];
    }

    private function csiLength(string $vdt, int $start): int
    {
        $i = $start + 2;
        while ($i < \strlen($vdt) && ord($vdt[$i]) >= 0x30 && ord($vdt[$i]) <= 0x3F) {
            $i++;
        }
        return $i - $start + 1;
    }
}
