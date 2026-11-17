<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

use MiniPaviService\Vdt\Screen\Attributes;

/**
 * Cuts Videotex content into Tokens
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
                    $glyph = substr($vdt, $i, 2);
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
                        substr($vdt, $i, 2),
                        count: ord($vdt[$i + 1] ?? "\x40") - 0x40,
                    );
                    $i++;
                    break;
                case 0x0E:
                case 0x0F:
                    $tokens[] = new Token(TokenType::Charset, $byte, $byte);
                    break;
                case 0x1F: // US: row, column as 0x40+n, or as 4 decimal digits
                    $first = $vdt[$i + 1] ?? '';
                    if ($first !== '' && ctype_digit($first)) {
                        $digits = substr($vdt, $i + 1, 4);
                        $tokens[] = new Token(
                            TokenType::Position,
                            "\x1F" . $digits,
                            row: (int) substr($digits, 0, 2),
                            col: (int) substr($digits, 2, 2),
                        );
                        $i += 4;
                    } else {
                        $tokens[] = new Token(
                            TokenType::Position,
                            substr($vdt, $i, 3),
                            row: ord($first) - 0x40,
                            col: ord($vdt[$i + 2] ?? "\x41") - 0x40,
                        );
                        $i += 2;
                    }
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
                    $tokens[] = new Token(TokenType::Raw, substr($vdt, $i, 2), substr($vdt, $i, 2));
                    $i++;
                    break;
                case 0x1B:
                    $sequence = $this->escapeSequence($vdt, $i);
                    $group = strlen($sequence) === 2 ? Attributes::group($sequence[1]) : null;
                    $tokens[] = $group !== null
                        ? new Token(TokenType::Attribute, $sequence, $sequence[1], group: $group)
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

    private function csiLength(string $vdt, int $start): int
    {
        $i = $start + 2;
        while ($i < \strlen($vdt) && ord($vdt[$i]) >= 0x30 && ord($vdt[$i]) <= 0x3F) {
            $i++;
        }
        return $i - $start + 1;
    }
}
