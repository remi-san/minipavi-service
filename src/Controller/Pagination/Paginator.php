<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Pagination;

/**
 * A page of items shown limit() at a time: which items it holds (offset(), limit(), slice()),
 * where it is among the pages (number(), pageCount(), isFirst(), isLast()), and the pages
 * around it (next(), previous()). There is always one page at least, empty if there are no
 * items. Immutable.
 */
final readonly class Paginator
{
    private int $index;

    /**
     * @param int $itemCount number of items, on all the pages
     * @param int $perPage number of items per page
     * @param int $index index of the page (from 0), kept within the pages (e.g. the items got
     *                   fewer since it was kept)
     *
     * @throws \InvalidArgumentException for a negative item count, or less than one item per page
     */
    public function __construct(
        private int $itemCount,
        private int $perPage,
        int $index,
    ) {
        if ($itemCount < 0) {
            throw new \InvalidArgumentException("Item count must not be negative, {$itemCount} given");
        }
        if ($perPage < 1) {
            throw new \InvalidArgumentException("At least one item per page, {$perPage} given");
        }
        $this->index = max(0, min($index, $this->pageCount() - 1));
    }

    /**
     * Number of items, on all the pages (also in toArray())
     */
    public function itemCount(): int
    {
        return $this->itemCount;
    }

    /**
     * Number of pages: 1 at least
     */
    public function pageCount(): int
    {
        return max(1, intdiv($this->itemCount + $this->perPage - 1, $this->perPage));
    }

    /**
     * Index of the page, from 0 (what PageParameters keeps)
     */
    public function index(): int
    {
        return $this->index;
    }

    /**
     * Number of the page, from 1 (what the user sees)
     */
    public function number(): int
    {
        return $this->index + 1;
    }

    /**
     * Index of the first item of the page, among all the items (from 0)
     */
    public function offset(): int
    {
        return $this->index * $this->perPage;
    }

    /**
     * Number of items to retrieve for the page, from offset(): the number per page (also in
     * toArray())
     */
    public function limit(): int
    {
        return $this->perPage;
    }

    /**
     * Number of items on the page: limit(), fewer on the last one
     */
    public function itemCountOnPage(): int
    {
        return min($this->perPage, $this->itemCount - $this->offset());
    }

    /**
     * Number of the first item of the page, from 1; 0 without items
     */
    public function firstItem(): int
    {
        return $this->itemCountOnPage() > 0 ? $this->offset() + 1 : 0;
    }

    /**
     * Number of the last item of the page, from 1; 0 without items
     */
    public function lastItem(): int
    {
        return $this->offset() + $this->itemCountOnPage();
    }

    public function isFirst(): bool
    {
        return $this->index === 0;
    }

    public function isLast(): bool
    {
        return $this->index === ($this->pageCount() - 1);
    }

    /**
     * The next page; null on the last one
     */
    public function next(): ?self
    {
        return $this->isLast() ? null : $this->at($this->index + 1);
    }

    /**
     * The previous page; null on the first one
     */
    public function previous(): ?self
    {
        return $this->isFirst() ? null : $this->at($this->index - 1);
    }

    /**
     * The page at index (from 0, kept within the pages), of the same items
     */
    public function at(int $index): self
    {
        return new self($this->itemCount, $this->perPage, $index);
    }

    /**
     * The items of the page, from all the items (a list of itemCount() items)
     *
     * @template T
     *
     * @param list<T> $items
     *
     * @return list<T>
     */
    public function slice(array $items): array
    {
        return \array_slice($items, $this->offset(), $this->limit());
    }

    /**
     * Variables of a template showing the page: {{page}} (its number, from 1), {{pageCount}},
     * {{itemCount}}, {{perPage}}, {{firstItem}} and {{lastItem}} (numbers from 1, 0 without
     * items)
     *
     * @return array{page: int, pageCount: int, itemCount: int, perPage: int, firstItem: int, lastItem: int}
     */
    public function toArray(): array
    {
        return [
            'page' => $this->number(),
            'pageCount' => $this->pageCount(),
            'itemCount' => $this->itemCount,
            'perPage' => $this->perPage,
            'firstItem' => $this->firstItem(),
            'lastItem' => $this->lastItem(),
        ];
    }
}
