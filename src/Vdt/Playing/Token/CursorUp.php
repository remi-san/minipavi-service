<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Playing\Token;

use MiniPaviService\Vdt\Playing\Pen;
use MiniPaviService\Vdt\Screen\Cursor;
use MiniPaviService\Vdt\Videotex;

/**
 * VT: moves the cursor 1 row up (Cursor::up())
 *
 * @internal
 */
final readonly class CursorUp extends ErasingNothing
{
    public function __construct()
    {
        parent::__construct(Videotex::CURSOR_UP);
    }

    public function playOn(Cursor $cursor, Pen $pen): void
    {
        $cursor->up();
    }
}
