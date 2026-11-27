<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Playing\Token;

use MiniPaviService\Vdt\Playing\Pen;
use MiniPaviService\Vdt\Screen\Attribute\Charset as ScreenCharset;
use MiniPaviService\Vdt\Screen\Cursor;

/**
 * SO (mosaic, G1) or SI (text, G0), the code of a Screen\Attribute\Charset: the character set
 * of what the cursor draws next (Cursor::switchCharset())
 *
 * @internal
 */
final readonly class Charset extends ErasingNothing
{
    public function __construct(
        private ScreenCharset $charset,
    ) {
        parent::__construct($charset->code());
    }

    public function playOn(Cursor $cursor, Pen $pen): void
    {
        $cursor->switchCharset($this->charset);
    }
}
