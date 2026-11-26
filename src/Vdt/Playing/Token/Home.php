<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Playing\Token;

use MiniPaviService\Vdt\Playing\Pen;
use MiniPaviService\Vdt\Screen\Cursor;
use MiniPaviService\Vdt\Videotex;

/**
 * RS: the cursor at row 1, column 1, its attributes back to their defaults (Cursor::reset())
 *
 * @internal
 */
final readonly class Home extends ErasingNothing
{
    public function __construct()
    {
        parent::__construct(Videotex::HOME);
    }

    public function playOn(Cursor $cursor, Pen $pen): void
    {
        $cursor->reset(1, col: 1);
    }
}
