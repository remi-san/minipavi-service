<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

/**
 * Videotex content (e.g. a .vdt page made with a Videotex editor), that can be moved on
 * the screen. Videotex positions are mostly absolute, but text also flows from one cell
 * to the next and wraps at the end of the row, so the content is played on a simulated
 * screen (play()) rather than having its positions patched.
 */
final readonly class Vdt
{
    /**
     * @param Token[] $tokens
     */
    private function __construct(
        public array $tokens,
    ) {}

    public static function fromString(string $vdt): self
    {
        return new self((new Parser())->parse($vdt));
    }

    public static function fromFile(string $file): self
    {
        $vdt = @file_get_contents($file);
        if ($vdt === false) {
            throw new \InvalidArgumentException("Videotex file not found: $file");
        }
        return self::fromString($vdt);
    }

    /**
     * The content as read, if it wasn't transformed
     */
    public function toString(): string
    {
        return implode('', array_map(fn(Token $token) => $token->source, $this->tokens));
    }

    public function screen(): Screen
    {
        return Screen::of($this);
    }

    /**
     * Content moved by x columns right and y rows down (negative: left, up). Characters that
     * end up off the screen are dropped (instead of wrapping onto the next row), and the
     * cursor is positioned again where needed, with the current attributes (positioning
     * resets them). Row 0, the status line, is not moved.
     */
    public function offset(int $x, int $y): self
    {
        $out = new Writer();
        $this->play(
            function (int $row, int $col, string $bytes, int $width, array $attributes) use ($out, $x, $y): void {
                // Row 0 stays where it is; the other rows stay within rows 1 to ROWS
                if ($row != 0) {
                    $row += $y;
                    $col += $x;
                    if ($row < 1 || $row > Screen::ROWS) {
                        return;
                    }
                }
                if ($col < 1 || $col + $width - 1 > Screen::COLS) {
                    return;
                }
                $out->glyph($row, $col, $bytes, $width, $attributes);
            },
            function (Token $token, Cursor $cursor) use ($out, $x, $y): void {
                switch ($token->type) {
                    case TokenType::Clear:
                        $out->clear();
                        break;
                    case TokenType::ClearLine:
                        if ($cursor->row == 0) {
                            $out->clearLine(0, $cursor->col, $cursor->attributes);
                            break;
                        }
                        $row = $cursor->row + $y;
                        if ($row < 1 || $row > Screen::ROWS) {
                            break;
                        }
                        // CAN clears up to the last column: moved left, the cleared cells must
                        // stop before the columns that came from beyond the screen, so they
                        // are written as spaces instead
                        if ($x < 0) {
                            for ($col = max(1, $cursor->col + $x); $col <= Screen::COLS + $x; $col++) {
                                $out->glyph($row, $col, ' ', 1, $cursor->attributes);
                            }
                        } elseif ($cursor->col + $x <= Screen::COLS) {
                            $out->clearLine($row, $cursor->col + $x, $cursor->attributes);
                        }
                        break;
                    case TokenType::Raw:
                        $out->raw($token->bytes);
                        break;
                }
            },
        );
        return self::fromString($out->vdt());
    }

    /**
     * Content that doesn't clear the screen, to be drawn over other content: its clear
     * screens (FF) only bring the cursor home, as RS
     */
    public function withoutClear(): self
    {
        return new self(array_map(
            fn(Token $token) => $token->type == TokenType::Clear ? new Token(TokenType::Home, "\x1E") : $token,
            $this->tokens,
        ));
    }

    /**
     * Plays the content on a simulated screen, calling glyph(row, col, bytes, width,
     * attributes) for each character (repetitions included), and other(token, cursor) for
     * the other tokens, once the cursor has followed them
     */
    public function play(callable $glyph, ?callable $other = null): void
    {
        $cursor = new Cursor();
        $last = null;
        foreach ($this->tokens as $token) {
            switch ($token->type) {
                case TokenType::Glyph:
                case TokenType::Repeat:
                    $repeat = $token->type == TokenType::Repeat;
                    $bytes = $repeat ? $last : $token->bytes;
                    if ($bytes === null) {
                        break;
                    }
                    for ($n = 0; $n < ($repeat ? $token->count : 1); $n++) {
                        $width = Attributes::width($cursor->attributes);
                        $glyph($cursor->row, $cursor->col, $bytes, $width, $cursor->attributes);
                        $cursor->advance($width);
                    }
                    $last = $bytes;
                    continue 2;
                case TokenType::Attribute:
                    $cursor->attributes[$token->group] = $token->bytes;
                    break;
                case TokenType::Charset:
                    $cursor->attributes['charset'] = $token->bytes;
                    break;
                case TokenType::Position:
                    $cursor->reset($token->row, max(1, $token->col));
                    break;
                case TokenType::Move:
                    $cursor->move($token->bytes);
                    break;
                case TokenType::Home:
                case TokenType::Clear:
                    $cursor->reset(1, 1);
                    break;
            }
            if ($other !== null) {
                $other($token, $cursor);
            }
        }
    }
}
