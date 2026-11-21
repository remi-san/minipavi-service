<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Token;

use MiniPaviService\Vdt\Pen;
use MiniPaviService\Vdt\Screen\Cursor;
use MiniPaviService\Vdt\Videotex;

/**
 * ESC and a byte setting an attribute (Screen\Attributes::with()) of what the cursor draws
 * next (Cursor::setAttribute())
 *
 * @internal
 */
final readonly class Attribute extends ErasingNothing
{
    /**
     * @param string $byte the byte after ESC
     */
    public function __construct(
        private string $byte,
    ) {
        parent::__construct(Videotex::ESC . $byte);
    }

    public function playOn(Cursor $cursor, Pen $pen): void
    {
        $cursor->setAttribute($this->byte);
    }
}
