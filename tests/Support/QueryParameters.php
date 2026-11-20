<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Controller\Parameters\Parameters;
use MiniPaviService\Controller\Parameters\Values;

/**
 * Parameters of a list of results: what was searched, and the page shown
 */
final readonly class QueryParameters implements Parameters
{
    public function __construct(
        public string $query = '',
        public int $page = 0,
    ) {}

    public static function fromValues(Values $values): static
    {
        return new self($values->string('query'), $values->int('page'));
    }

    public function toArray(): array
    {
        return ['query' => $this->query, 'page' => $this->page];
    }
}
