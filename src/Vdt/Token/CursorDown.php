<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Token;

use MiniPaviService\Vdt\Pen;
use MiniPaviService\Vdt\Screen\Cursor;
use MiniPaviService\Vdt\Videotex;

/**
 * LF: moves the cursor 1 row down (Cursor::down())
 *
 * @internal
 */
final readonly class CursorDown extends ErasingNothing
{
    public function __construct()
    {
        parent::__construct(Videotex::CURSOR_DOWN);
    }

    public function playOn(Cursor $cursor, Pen $pen): void
    {
        $cursor->down();
    }
}
