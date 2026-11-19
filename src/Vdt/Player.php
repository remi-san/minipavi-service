<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

use MiniPaviService\Vdt\Screen\Cursor;

/**
 * Plays Videotex content on a simulated Minitel screen in page mode: follows the cursor and
 * the attributes as the content moves and sets them, and tells what it draws. As the Minitel
 * does, it discards ESC M (double height) and ESC O (double size) received on rows 0 and 1,
 * the size staying as it was; a double height already set when a cursor move brings the
 * cursor there is kept (the character drawn over the row above). A tall character wrapping
 * at the end of a row goes 2 rows down (Cursor::advance()); a move right (HT) from the last
 * column goes 1 row down, as BS, LF and VT do. SO and SI set the size back to normal and
 * turn off inverse video and separation (SO also underline), and a size or inverse video
 * received in mosaic is discarded. Underline, background colour and masking in text are a
 * zone's, which a delimiter starts (Screen\Zone). What Screen and Offset are built on.
 *
 * @internal
 */
final readonly class Player
{
    /**
     * @param Parser $parser cuts the content into tokens; stateless, so one can be shared
     */
    public function __construct(
        private Parser $parser = new Parser(),
    ) {}

    /**
     * Plays the content, telling the listener each character drawn (repetitions included),
     * delimiter or CAN, and the other tokens once the cursor has followed them
     */
    public function play(Vdt $vdt, ScreenListener $listener): void
    {
        $cursor = new Cursor();
        $last = null;
        foreach ($this->parser->parse($vdt->toString()) as $token) {
            switch ($token->type) {
                case TokenType::Glyph:
                case TokenType::Repeat:
                    $repeat = $token->type === TokenType::Repeat;
                    $bytes = $repeat ? $last : $token->bytes;
                    // REP with no character before: nothing to draw, nor to tell
                    if ($bytes !== null) {
                        for ($n = 0; $n < ($repeat ? $token->count : 1); $n++) {
                            $this->draw($cursor, $bytes, $listener);
                        }
                        $last = $bytes;
                    }
                    continue 2;
                case TokenType::Attribute:
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
                case TokenType::ClearLine:
                    $this->clearLine($cursor, $listener);
                    continue 2;
                case TokenType::Home:
                case TokenType::Clear:
                    $cursor->reset(1, col: 1);
                    break;
            }
            $listener->sequence($token);
        }
    }

    /**
     * CAN at the cursor: clears to the end of its row with spaces, the first one a delimiter
     * while a zone attribute waits (Cursor::delimits()), as when the content draws them
     */
    private function clearLine(Cursor $cursor, ScreenListener $listener): void
    {
        $delimiter = null;
        if ($cursor->delimits(' ')) {
            $cursor->delimit();
            $delimiter = $cursor->zone();
        }
        $listener->clearLine($cursor->row(), $cursor->col(), $cursor->attributes(), $delimiter);
    }

    /**
     * Draws a character at the cursor, or a delimiter (Cursor::delimits()), and moves past it
     */
    private function draw(Cursor $cursor, string $bytes, ScreenListener $listener): void
    {
        $width = $cursor->attributes()->width();
        if (!$cursor->delimits($bytes)) {
            $listener->glyph($cursor->row(), $cursor->col(), $bytes, $width, $cursor->attributes());
            $cursor->advance($width);
            return;
        }
        $cursor->delimit();
        $listener->delimiter($cursor->row(), $cursor->col(), $width, $cursor->attributes(), $cursor->zone());
        $cursor->advance($width);
    }
}
