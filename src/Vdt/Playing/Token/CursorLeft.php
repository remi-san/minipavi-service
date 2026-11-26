<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Playing\Token;

use MiniPaviService\Vdt\Playing\Pen;
use MiniPaviService\Vdt\Screen\Cursor;
use MiniPaviService\Vdt\Videotex;

/**
 * BS: moves the cursor 1 column left (Cursor::left())
 *
 * @internal
 */
final readonly class CursorLeft extends ErasingNothing
{
    public function __construct()
    {
        parent::__construct(Videotex::CURSOR_LEFT);
    }

    public function playOn(Cursor $cursor, Pen $pen): void
    {
        $cursor->left();
    }
}
