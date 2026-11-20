<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Token;

use MiniPaviService\Vdt\Pen;
use MiniPaviService\Vdt\Screen\Cursor;

/**
 * REP: draws the last character again, count times
 *
 * @internal
 */
final readonly class Repeat extends ErasingNothing
{
    /**
     * @param string $source REP and its byte (0x40 + count)
     */
    public function __construct(
        string $source,
        private int $count,
    ) {
        parent::__construct($source);
    }

    public function playOn(Cursor $cursor, Pen $pen): void
    {
        $pen->repeat($cursor, $this->count);
    }
}
