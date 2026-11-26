<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Playing\Token;

use MiniPaviService\Vdt\Playing\Pen;
use MiniPaviService\Vdt\Screen\Cursor;
use MiniPaviService\Vdt\Videotex;

/**
 * HT: moves the cursor 1 column right (Cursor::right())
 *
 * @internal
 */
final readonly class CursorRight extends ErasingNothing
{
    public function __construct()
    {
        parent::__construct(Videotex::CURSOR_RIGHT);
    }

    public function playOn(Cursor $cursor, Pen $pen): void
    {
        $cursor->right();
    }
}
