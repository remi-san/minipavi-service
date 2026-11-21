<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Token;

use MiniPaviService\Vdt\Pen;
use MiniPaviService\Vdt\Screen\Cursor;

/**
 * SO (mosaic, G1) or SI (text, G0): the character set of what the cursor draws next
 * (Cursor::setCharset())
 *
 * @internal
 */
final readonly class Charset extends ErasingNothing
{
    /**
     * @param string $byte SO or SI
     */
    public function __construct(
        private string $byte,
    ) {
        parent::__construct($byte);
    }

    public function playOn(Cursor $cursor, Pen $pen): void
    {
        $cursor->setCharset($this->byte);
    }
}
