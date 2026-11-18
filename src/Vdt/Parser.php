<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

use MiniPaviService\Vdt\Screen\Attributes;

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
                $tokens[] = new Token(TokenType::Glyph, $byte, $byte);
                continue;
            }
            switch ($byte) {
                case Videotex::G2: // G2 character, possibly an accent followed by its letter
                    $glyph = substr($vdt, $i, length: 2);
                    $accent = ord($vdt[$i + 1] ?? "\0");
                    if ($accent >= 0x41 && $accent <= 0x4F && ($i + 2) < \strlen($vdt) && ord($vdt[$i + 2]) >= 0x20) {
                        $glyph .= $vdt[$i + 2];
                    }
                    $tokens[] = new Token(TokenType::Glyph, $glyph, $glyph);
                    $i += strlen($glyph) - 1;
                    break;
                case Videotex::SUB: // shown as an error character
                    $tokens[] = new Token(TokenType::Glyph, $byte, $byte);
                    break;
                case Videotex::REPEAT:
                    $tokens[] = new Token(
                        TokenType::Repeat,
                        substr($vdt, $i, length: 2),
                        count: ord($vdt[$i + 1] ?? "\x40") - 0x40,
                    );
                    $i++;
                    break;
                case Videotex::MOSAIC_SET:
                case Videotex::TEXT_SET:
                    $tokens[] = new Token(TokenType::Charset, $byte, $byte);
                    break;
                case Videotex::POSITION: // row, column as 0x40+n, or as 4 decimal digits
                    [$position, $skipped] = self::position($vdt, $i);
                    $tokens[] = $position;
                    $i += $skipped;
                    break;
                case Videotex::CURSOR_LEFT:
                case Videotex::CURSOR_RIGHT:
                case Videotex::CURSOR_DOWN:
                case Videotex::CURSOR_UP:
                case Videotex::CARRIAGE_RETURN:
                    $tokens[] = new Token(TokenType::Move, $byte, $byte);
                    break;
                case Videotex::HOME:
                    $tokens[] = new Token(TokenType::Home, $byte);
                    break;
                case Videotex::CLEAR_SCREEN:
                    $tokens[] = new Token(TokenType::Clear, $byte);
                    break;
                case Videotex::CLEAR_LINE:
                    $tokens[] = new Token(TokenType::ClearLine, $byte);
                    break;
                case Videotex::SEP: // one byte follows each
                case Videotex::SS3:
                    $tokens[] = new Token(TokenType::Raw, substr($vdt, $i, length: 2), substr($vdt, $i, length: 2));
                    $i++;
                    break;
                case Videotex::ESC:
                    $sequence = $this->escapeSequence($vdt, $i);
                    $tokens[] = strlen($sequence) === 2 && Attributes::isAttribute($sequence[1])
                        ? new Token(TokenType::Attribute, $sequence, $sequence[1])
                        : new Token(TokenType::Raw, $sequence, $sequence);
                    $i += strlen($sequence) - 1;
                    break;
                default:
                    $tokens[] = new Token(TokenType::Raw, $byte, $byte);
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
     * Position (US) at offset i: the token, and how many bytes it takes after US
     *
     * @return array{Token, int}
     */
    private static function position(string $vdt, int $i): array
    {
        $first = $vdt[$i + 1] ?? '';
        if ($first !== '' && ctype_digit($first)) {
            $digits = substr($vdt, $i + 1, length: 4);

            return [
                new Token(
                    TokenType::Position,
                    Videotex::POSITION . $digits,
                    row: (int) substr($digits, offset: 0, length: 2),
                    col: (int) substr($digits, offset: 2, length: 2),
                ),
                4,
            ];
        }

        return [
            new Token(
                TokenType::Position,
                substr($vdt, $i, length: 3),
                row: ord($first) - 0x40,
                col: ord($vdt[$i + 2] ?? "\x41") - 0x40,
            ),
            2,
        ];
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
