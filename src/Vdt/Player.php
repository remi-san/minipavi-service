<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

use MiniPaviService\Vdt\Screen\Cursor;

/**
 * Plays Videotex content on a simulated Minitel screen in page mode: follows the cursor and
 * the attributes as the content moves and sets them, and tells what it draws. What Screen
 * and Offset are built on.
 *
 * @internal
 */
final readonly class Player
{
    /**
     * Plays the content, telling the listener each character drawn (repetitions included),
     * and the other tokens once the cursor has followed them (a copy of the cursor)
     */
    public function play(Vdt $vdt, ScreenListener $listener): void
    {
        $cursor = new Cursor();
        $last = null;
        foreach ((new Parser())->parse($vdt->toString()) as $token) {
            switch ($token->type) {
                case TokenType::Glyph:
                case TokenType::Repeat:
                    $repeat = $token->type === TokenType::Repeat;
                    $bytes = $repeat ? $last : $token->bytes;
                    // REP with no character before: nothing to draw, nor to tell
                    if ($bytes === null) {
                        continue 2;
                    }
                    for ($n = 0; $n < ($repeat ? $token->count : 1); $n++) {
                        $width = $cursor->attributes->width();
                        $listener->glyph($cursor->row, $cursor->col, $bytes, $width, $cursor->attributes);
                        $cursor->advance($width);
                    }
                    $last = $bytes;
                    continue 2;
                case TokenType::Attribute:
                    $cursor->attributes = $cursor->attributes->with($token->bytes);
                    break;
                case TokenType::Charset:
                    $cursor->attributes = $cursor->attributes->withCharset($token->bytes);
                    break;
                case TokenType::Position:
                    $cursor->reset($token->row, max(1, $token->col));
                    break;
                case TokenType::Move:
                    $cursor->move($token->bytes);
                    break;
                case TokenType::Home:
                case TokenType::Clear:
                    $cursor->reset(1, col: 1);
                    break;
            }
            // A copy: what the listener does with it doesn't move what follows
            $listener->sequence($token, clone $cursor);
        }
    }
}
