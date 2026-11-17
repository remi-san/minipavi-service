<?php

declare(strict_types=1);

namespace MiniPaviService\Controller;

/**
 * Parameters of a PaginatedController: the page shown, from 0
 */
final readonly class PageParameters implements Parameters
{
    public function __construct(
        public int $page = 0,
    ) {}

    // A page missing or invalid (e.g. negative) is the first one
    public static function fromValues(Values $values): static
    {
        return new self(max(0, $values->int('page')));
    }

    public function toArray(): array
    {
        return ['page' => $this->page];
    }
}
