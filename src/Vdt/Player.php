<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Cursor;

/**
 * Plays Videotex content on a simulated Minitel screen in page mode: follows the cursor and
 * the attributes as the content moves and sets them, and tells what it draws. As the Minitel
 * does, it discards ESC M (double height) and ESC O (double size) received on rows 0 and 1,
 * the size staying as it was; a double height already set when a cursor move brings the
 * cursor there is kept (the character drawn over the row above). What Screen
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
                        $width = $cursor->attributes()->width();
                        $listener->glyph($cursor->row(), $cursor->col(), $bytes, $width, $cursor->attributes());
                        $cursor->advance($width);
                    }
                    $last = $bytes;
                    continue 2;
                case TokenType::Attribute:
                    // The Minitel discards ESC M and ESC O received on a row without double
                    // height (the size stays as it was); ESC N, and the other attributes, apply
                    if (!Attributes::allowsDoubleHeight($cursor->row()) && Attributes::isTallSize($token->bytes)) {
                        break;
                    }
                    $cursor->setAttribute($token->bytes);
                    break;
                case TokenType::Charset:
                    $cursor->setCharset($token->bytes);
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
