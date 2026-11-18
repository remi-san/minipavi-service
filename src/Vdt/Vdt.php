<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

use MiniPaviService\Vdt\Screen\Screen;

/**
 * Videotex content: what is sent to the Minitel. The framework and services carry it as this
 * value, and only the gateway client turns it into bytes (toString()). Text is made with
 * text(), which encodes its accents (G2); raw Videotex with new Vdt() or fromString().
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
     * Raw Videotex
     */
    public static function fromString(string $bytes): self
    {
        return new self($bytes);
    }

    /**
     * Text (UTF-8), its accented and special characters encoded in G2; control characters
     * become spaces
     */
    public static function text(string $text): self
    {
        return new self(VdtHelper::toG2($text)->toString());
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
            ? "\x1E"
            : $token->source, (new Parser())->parse($this->bytes))));
    }
}
