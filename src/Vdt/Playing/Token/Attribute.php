<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Playing\Token;

use MiniPaviService\Vdt\Playing\Pen;
use MiniPaviService\Vdt\Screen\Attribute\Attribute as ScreenAttribute;
use MiniPaviService\Vdt\Screen\Cursor;

/**
 * ESC and the byte of a Screen\Attribute\Attribute, which sets it on what the cursor draws
 * next (Cursor::setAttribute())
 *
 * @internal
 */
final readonly class Attribute extends ErasingNothing
{
    public function __construct(
        private ScreenAttribute $attribute,
    ) {
        parent::__construct($attribute->code());
    }

    public function playOn(Cursor $cursor, Pen $pen): void
    {
        $cursor->setAttribute($this->attribute);
    }
}
