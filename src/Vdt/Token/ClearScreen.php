<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Token;

use MiniPaviService\Vdt\Pen;
use MiniPaviService\Vdt\Screen\Cursor;
use MiniPaviService\Vdt\Videotex;

/**
 * FF: clears the screen but row 0, the status line, and brings the cursor home, as RS
 *
 * @internal
 */
final readonly class ClearScreen implements Token
{
    public function playOn(Cursor $cursor, Pen $pen): void
    {
        $cursor->reset(1, col: 1);
        $pen->clearScreen();
    }

    /**
     * FF alone: it erases the content before it
     */
    public function appendTo(string $content): string
    {
        return Videotex::CLEAR_SCREEN;
    }

    /**
     * RS: over other content, it only brings the cursor home
     */
    public function drawnOver(): string
    {
        return Videotex::HOME;
    }
}
