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
            $code = ord($byte);
            if ($code >= 0x20) {
                $tokens[] = new Token(TokenType::Glyph, $byte, $byte);
                continue;
            }
            switch ($code) {
                case 0x19: // SS2: G2 character, possibly an accent followed by its letter
                    $glyph = substr($vdt, $i, length: 2);
                    $accent = ord($vdt[$i + 1] ?? "\0");
                    if ($accent >= 0x41 && $accent <= 0x4F && ($i + 2) < \strlen($vdt) && ord($vdt[$i + 2]) >= 0x20) {
                        $glyph .= $vdt[$i + 2];
                    }
                    $tokens[] = new Token(TokenType::Glyph, $glyph, $glyph);
                    $i += strlen($glyph) - 1;
                    break;
                case 0x1A: // SUB, shown as an error character
                    $tokens[] = new Token(TokenType::Glyph, $byte, $byte);
                    break;
                case 0x12: // REP
                    $tokens[] = new Token(
                        TokenType::Repeat,
                        substr($vdt, $i, length: 2),
                        count: ord($vdt[$i + 1] ?? "\x40") - 0x40,
                    );
                    $i++;
                    break;
                case 0x0E:
                case 0x0F:
                    $tokens[] = new Token(TokenType::Charset, $byte, $byte);
                    break;
                case 0x1F: // US: row, column as 0x40+n, or as 4 decimal digits
                    [$position, $skipped] = self::position($vdt, $i);
                    $tokens[] = $position;
                    $i += $skipped;
                    break;
                case 0x08:
                case 0x09:
                case 0x0A:
                case 0x0B:
                case 0x0D:
                    $tokens[] = new Token(TokenType::Move, $byte, $byte);
                    break;
                case 0x1E:
                    $tokens[] = new Token(TokenType::Home, $byte);
                    break;
                case 0x0C:
                    $tokens[] = new Token(TokenType::Clear, $byte);
                    break;
                case 0x18:
                    $tokens[] = new Token(TokenType::ClearLine, $byte);
                    break;
                case 0x13: // SEP and SS3: one byte follows
                case 0x1D:
                    $tokens[] = new Token(TokenType::Raw, substr($vdt, $i, length: 2), substr($vdt, $i, length: 2));
                    $i++;
                    break;
                case 0x1B:
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
                    "\x1F" . $digits,
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
