<?php

declare(strict_types=1);

namespace MiniPaviService\Page;

use MiniPaviService\Page\Display\Display;

/**
 * Content always the same (<page type="static">): a Display, data unused
 */
final readonly class StaticContent implements Content
{
    public function __construct(
        private Display $display,
    ) {}

    public function display(array $data): Display
    {
        return $this->display;
    }
}
