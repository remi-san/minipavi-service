<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Playing\Token;

use MiniPaviService\Vdt\Playing\Pen;
use MiniPaviService\Vdt\Screen\Cursor;

/**
 * Part of Videotex content, as Parser cuts it: a character or a sequence, which plays what
 * it does on the screen, and keeps the bytes it was read from
 *
 * @internal
 */
interface Token
{
    /**
     * Plays the token: moves the cursor or sets its attributes, or draws at it with the pen
     */
    public function playOn(Cursor $cursor, Pen $pen): void;

    /**
     * Content followed by this token's bytes as read, without what the token erases: a
     * clear screen (FF) erases the content before it (ClearScreens::fromLast())
     */
    public function appendTo(string $content): string;

    /**
     * The bytes of this token, to draw it over other content: as read, but a clear screen
     * (FF) only brings the cursor home, as RS (ClearScreens::removed())
     */
    public function drawnOver(): string;
}
