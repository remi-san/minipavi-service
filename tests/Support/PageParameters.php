<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Controller\Parameters;
use MiniPaviService\Controller\Request;

/**
 * Parameters of a paged screen: the page shown, from 0
 */
final readonly class PageParameters implements Parameters
{
    public function __construct(
        public int $page = 0,
    ) {}

    public static function fromRequest(Request $request): static
    {
        return new self(max(0, $request->int('page')));
    }

    public function toArray(): array
    {
        return ['page' => $this->page];
    }
}
