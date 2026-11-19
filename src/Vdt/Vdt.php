<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

use MiniPaviService\InvalidValueException;
use MiniPaviService\Minitel\Position;

/**
 * Videotex content: what is sent to the Minitel. The framework and services carry it as this
 * value, and only the gateway client turns it into bytes (toString()). Text is made with
 * text(), which encodes its accents (G2); positions, the status line, a clear screen and
 * repetitions with position(), line0(), clearScreen() and repeat(), which encode as
 * MiniPaviCli's helpers do; raw Videotex with new Vdt().
 *
 * It keeps the bytes, and doesn't draw them itself: what the content shows is Screen's
 * (Screen::of()), moving it Offset's (Offset::apply()), both played by Player. A file is read by its
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
        return new self(G2::encode($text));
    }

    /**
     * Positions the cursor at a cell (row 0 is the status line)
     */
    public static function position(Position $position): self
    {
        return new self(Videotex::POSITION . \chr(0x40 + $position->row) . \chr(0x40 + $position->col));
    }

    /**
     * Text (encoded as text() does) on row 0, the status line, the rest of the row cleared,
     * then leaves the status line
     */
    public static function line0(string $text): self
    {
        return new self(
            self::position(new Position(row: 0))->bytes
            . G2::encode($text)
            . Videotex::BLINK_OFF
            . Videotex::CLEAR_LINE
            . Videotex::CURSOR_DOWN,
        );
    }

    /**
     * Clears the screen and the status line, and turns the cursor off, at row 1, column 1
     */
    public static function clearScreen(): self
    {
        // Row 0 cleared with spaces, as FF doesn't clear it, then the rest of the screen
        return self::join(
            self::position(new Position(row: 0)),
            new self(' '),
            self::repeat(' ', count: 39),
            self::position(new Position(row: 1)),
            new self(Videotex::CLEAR_SCREEN . Videotex::CURSOR_OFF),
        );
    }

    /**
     * A character, count times in all, as REP sends it: the character, then REP and how many
     * more times, 63 at most (0x7F) for its count to stay a 7-bit byte; beyond, more REPs follow,
     * as the MiniPavi emulator's own encoder writes them
     *
     * @throws InvalidValueException for a count below 1
     */
    public static function repeat(string $char, int $count): self
    {
        if ($count < 1) {
            throw new InvalidValueException("A character is repeated once at least, got {$count}");
        }
        $bytes = $char;
        $more = $count - 1;
        while ($more > Videotex::MAX_REPEAT) {
            $bytes .= Videotex::REPEAT . \chr(0x40 + Videotex::MAX_REPEAT);
            $more -= Videotex::MAX_REPEAT;
        }

        return new self($bytes . Videotex::REPEAT . \chr(0x40 + $more));
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
     * The bytes sent to the Minitel
     */
    public function toString(): string
    {
        return $this->bytes;
    }
}
