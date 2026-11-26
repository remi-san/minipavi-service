<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Playing\Token;

use MiniPaviService\Vdt\Playing\Pen;
use MiniPaviService\Vdt\Screen\Cursor;

/**
 * Bytes sent as they are, which don't move the cursor nor draw (protocol, cursor on or off,
 * bell, SEP or SS3 and their byte, an escape sequence other than an attribute, a G2
 * character cut at the end of the content...)
 *
 * @internal
 */
final readonly class Raw extends ErasingNothing
{
    public function __construct(
        private string $bytes,
    ) {
        parent::__construct($bytes);
    }

    public function playOn(Cursor $cursor, Pen $pen): void
    {
        $pen->raw($this->bytes);
    }
}
