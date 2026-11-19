<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

use MiniPavi\MiniPaviCli;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Vdt\Screen\Screen;

/**
 * Videotex content: what is sent to the Minitel. The framework and services carry it as this
 * value, and only the gateway client turns it into bytes (toString()). Text is made with
 * text(), which encodes its accents (G2); positions, the status line, a clear screen and
 * repetitions with position(), line0(), clearScreen() and repeat(), MiniPaviCli's helpers
 * as Vdt; raw Videotex with new Vdt().
 *
 * It keeps the bytes, and doesn't draw them itself: what the content shows is Screen's
 * (screen()), moving it Offset's (offset()), both played by Player. A file is read by its
 * caller (e.g. ElementFactory), with its own error.
 */
final readonly class Vdt
{
    /**
     * @param string $bytes Videotex, as sent; '' for nothing
     */
    public function __construct(
        private string $bytes = '',
    ) {}

    /**
     * Text (UTF-8), its accented and special characters encoded in G2; control characters
     * become spaces
     */
    public static function text(string $text): self
    {
        return new self(MiniPaviCli::toG2($text));
    }

    /**
     * Positions the cursor at a cell (row 0 is the status line)
     */
    public static function position(Position $position): self
    {
        return new self(MiniPaviCli::setPos($position->col, $position->row));
    }

    /**
     * Text (encoded as text() does) on row 0, the status line, the rest of the row cleared,
     * then leaves the status line
     */
    public static function line0(string $text): self
    {
        return new self(MiniPaviCli::writeLine0($text));
    }

    /**
     * Clears the screen and the status line, and turns the cursor off, at row 1, column 1
     */
    public static function clearScreen(): self
    {
        return new self(MiniPaviCli::clearScreen());
    }

    /**
     * A character, count times in all, as REP sends it
     */
    public static function repeat(string $char, int $count): self
    {
        return new self(MiniPaviCli::repeatChar($char, $count));
    }

    /**
     * Contents one after the other
     */
    public static function join(self ...$parts): self
    {
        return new self(implode('', array_map(static fn(self $part) => $part->bytes, $parts)));
    }

    /**
     * This content, then others
     */
    public function append(self ...$parts): self
    {
        return self::join($this, ...$parts);
    }

    public function isEmpty(): bool
    {
        return $this->bytes === '';
    }

    /**
     * The content from its last clear screen (FF), which erases what comes before it but row
     * 0: what still shows, without the content drawn over since
     */
    public function fromLastClear(): self
    {
        $tokens = (new Parser())->parse($this->bytes);
        $start = 0;
        foreach ($tokens as $i => $token) {
            if ($token->type !== TokenType::Clear) {
                continue;
            }

            $start = $i;
        }

        return new self(implode('', array_map(
            static fn(Token $token) => $token->source,
            \array_slice($tokens, $start),
        )));
    }

    /**
     * The bytes sent to the Minitel
     */
    public function toString(): string
    {
        return $this->bytes;
    }

    public function screen(): Screen
    {
        return Screen::of($this);
    }

    /**
     * Content moved by x columns right and y rows down (negative: left, up): see Offset
     */
    public function offset(int $x, int $y): self
    {
        return (new Offset($x, $y))->apply($this);
    }

    /**
     * Content that doesn't clear the screen, to be drawn over other content: its clear
     * screens (FF) only bring the cursor home, as RS
     */
    public function withoutClear(): self
    {
        return new self(implode('', array_map(static fn(Token $token) => $token->type === TokenType::Clear
            ? Videotex::HOME
            : $token->source, (new Parser())->parse($this->bytes))));
    }
}
