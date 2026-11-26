<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Playing;

use MiniPaviService\Vdt\Screen\Cursor;
use MiniPaviService\Vdt\Vdt;

/**
 * Plays Videotex content on a simulated Minitel screen in page mode: follows the cursor and
 * the attributes as the content moves and sets them, and tells what it draws. As the Minitel
 * does, it discards ESC M (double height) and ESC O (double size) received on rows 0 and 1,
 * and ESC N (double width) on row 0, the size staying as it was, and a positioning outside the screen (Cursor::position()); a
 * double height already set when a cursor move brings the cursor there is kept (the
 * character drawn over the row above). A tall character wrapping at the end of a row goes 2
 * rows down (Cursor::advance()); a move right (HT) from the last column goes 1 row down, as
 * BS, LF and VT do. On the status row (row 0), VT is ignored and BS stays on it, while LF,
 * and HT from its last column, go back to where the cursor was, with its attributes, when a
 * positioning brought it there (Cursor::position()). SO and SI set the size back to normal
 * and turn off inverse video and separation (SO also underline), and a size or inverse video
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
     * delimiter, CAN, FF and the bytes sent as they are: each token plays what it does (Token)
     */
    public function play(Vdt $vdt, ScreenListener $listener): void
    {
        $cursor = new Cursor();
        $pen = new Pen($listener);
        foreach ($this->parser->parse($vdt->toString()) as $token) {
            $token->playOn($cursor, $pen);
        }
    }
}
