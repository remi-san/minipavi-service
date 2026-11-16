<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Display;

use MiniPaviService\Core\Terminal;

/**
 * Something drawn on the screen by a Display. The screen is 40 columns x 24 rows,
 * row 0 being the status line.
 */
interface Element
{
    /**
     * Videotex content drawing the element, for the user's terminal (which only some
     * elements depend on, e.g. EndKey)
     */
    public function getContent(Terminal $terminal): string;
}
