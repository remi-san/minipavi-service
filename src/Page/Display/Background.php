<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Display;

use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Vdt\Vdt;

/**
 * Videotex content sent as is, e.g. a page made with a Videotex editor
 * (<background> in the page XML format). Put it first to draw over it.
 */
final readonly class Background implements Element
{
    public function __construct(
        private Vdt $vdt,
    ) {}

    public function toVdt(Terminal $terminal): Vdt
    {
        return $this->vdt;
    }
}
