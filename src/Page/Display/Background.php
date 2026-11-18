<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Display;

use MiniPaviService\Minitel\Terminal;

/**
 * Videotex content sent as is, e.g. a page made with a Videotex editor
 * (<background> in the page XML format). Put it first to draw over it.
 */
final readonly class Background implements Element
{
    public function __construct(
        public string $vdt,
    ) {}

    public function getContent(Terminal $terminal): string
    {
        return $this->vdt;
    }
}
