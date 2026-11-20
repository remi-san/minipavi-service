<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Token;

use MiniPaviService\Vdt\Pen;
use MiniPaviService\Vdt\Screen\Cursor;

/**
 * A character, taking one cell (two in double width): a byte from 0x20, a G2 character
 * (SS2, possibly an accent, and its letter), or SUB, shown as an error character
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
