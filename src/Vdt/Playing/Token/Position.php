<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Playing\Token;

use MiniPaviService\Vdt\Playing\Pen;
use MiniPaviService\Vdt\Screen\Cursor;

/**
 * US: positions the cursor at row, col, ignored outside the screen (Cursor::position())
 *
 * @internal
 */
final readonly class Position extends ErasingNothing
{
    /**
     * @param string $source US and its 2 bytes
     */
    public function __construct(
        string $source,
        private int $row,
        private int $col,
    ) {
        parent::__construct($source);
    }

    public function playOn(Cursor $cursor, Pen $pen): void
    {
        $cursor->position($this->row, $this->col);
    }
}
