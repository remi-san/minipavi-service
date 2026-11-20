<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Display;

use MiniPaviService\InvalidValueException;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Vdt\ClearScreens;
use MiniPaviService\Vdt\Offset;
use MiniPaviService\Vdt\Vdt;

/**
 * Videotex picture (e.g. made with a Videotex editor) drawn with its top-left cell, row 1
 * column 1 of the file, at row and col (<image> in the page XML format). What goes past the
 * edges of the screen is cut, and the picture doesn't clear the screen: it is drawn over
 * what is already there.
 */
final readonly class Image implements Element
{
    // The picture, moved to its place
    private Vdt $vdt;

    public function __construct(
        public Vdt $image,
        public Position $position,
    ) {
        if ($position->row < 1) {
            throw new InvalidValueException('An image starts at row 1 at least');
        }
        $this->vdt = (new Offset($position->col - 1, $position->row - 1))->apply((new ClearScreens())->removed($image));
    }

    public function toVdt(Terminal $terminal): Vdt
    {
        return $this->vdt;
    }
}
