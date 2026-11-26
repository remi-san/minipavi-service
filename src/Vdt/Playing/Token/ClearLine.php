<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Playing\Token;

use MiniPaviService\Vdt\Playing\Pen;
use MiniPaviService\Vdt\Screen\Cursor;
use MiniPaviService\Vdt\Videotex;

/**
 * CAN: clears the row of the cursor to its end (Pen::clearLine())
 *
 * @internal
 */
final readonly class ClearLine extends ErasingNothing
{
    public function __construct()
    {
        parent::__construct(Videotex::CLEAR_LINE);
    }

    public function playOn(Cursor $cursor, Pen $pen): void
    {
        $pen->clearLine($cursor);
    }
}
