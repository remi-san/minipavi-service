<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Playing\Token;

use MiniPaviService\Vdt\Playing\Pen;
use MiniPaviService\Vdt\Screen\Cursor;
use MiniPaviService\Vdt\Videotex;

/**
 * CR: moves the cursor to the first column of its row (Cursor::carriageReturn())
 *
 * @internal
 */
final readonly class CarriageReturn extends ErasingNothing
{
    public function __construct()
    {
        parent::__construct(Videotex::CARRIAGE_RETURN);
    }

    public function playOn(Cursor $cursor, Pen $pen): void
    {
        $cursor->carriageReturn();
    }
}
