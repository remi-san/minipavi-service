<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Playing\Token;

use MiniPaviService\Vdt\Playing\Pen;
use MiniPaviService\Vdt\Screen\Cursor;

/**
 * A character, taking one cell (two in double width): a byte from 0x20, a G2 character
 * (SS2 or SYN, its code, and after an accent the byte after it), or SUB, shown as an error
 * character
 *
 * @internal
 */
final readonly class Glyph extends ErasingNothing
{
    public function __construct(
        private string $bytes,
    ) {
        parent::__construct($bytes);
    }

    public function playOn(Cursor $cursor, Pen $pen): void
    {
        $pen->draw($cursor, $this->bytes);
    }
}
