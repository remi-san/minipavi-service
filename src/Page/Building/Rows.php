<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Building;

/**
 * Rows given relative to a row of the page: on the page, from row 0 (each row is itself);
 * inside an item of a <foreach>, from the item's first row (0 = its first row)
 */
final readonly class Rows
{
    /**
     * @param int $first row of the page where the rows given start
     */
    public function __construct(
        private int $first = 0,
    ) {}

    /**
     * Row of the page of a row given here
     */
    public function rowOf(int $row): int
    {
        return $this->first + $row;
    }
}
