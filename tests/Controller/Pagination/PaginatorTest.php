<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Controller\Pagination;

use MiniPaviService\Controller\Pagination\Paginator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PaginatorTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function pageCounts(): iterable
    {
        yield 'no items: one empty page' => [0, 4, 1];
        yield 'fewer than a page' => [3, 4, 1];
        yield 'a full page' => [4, 4, 1];
        yield 'a page and one item' => [5, 4, 2];
        yield 'full pages' => [8, 4, 2];
        yield 'one per page' => [5, 1, 5];
    }

    #[DataProvider('pageCounts')]
    public function testCountsThePages(int $itemCount, int $perPage, int $pageCount): void
    {
        self::assertSame($pageCount, (new Paginator($itemCount, $perPage, 0))->pageCount());
    }

    public function testGivesWhatToRetrieveForThePage(): void
    {
        $paginator = new Paginator(10, 4, 1);

        self::assertSame(10, $paginator->itemCount());
        self::assertSame(1, $paginator->index());
        self::assertSame(2, $paginator->number());
        self::assertSame(4, $paginator->offset());
        self::assertSame(4, $paginator->limit());
        self::assertSame(4, $paginator->itemCountOnPage());
        self::assertSame([5, 8], [$paginator->firstItem(), $paginator->lastItem()]);
    }

    public function testLastPageHoldsTheRest(): void
    {
        $paginator = new Paginator(10, 4, 2);

        self::assertSame(8, $paginator->offset());
        self::assertSame(2, $paginator->itemCountOnPage());
        self::assertSame([9, 10], [$paginator->firstItem(), $paginator->lastItem()]);
        self::assertSame(['i', 'j'], $paginator->slice(range('a', end: 'j')));
    }

    public function testEmptyPageHasNoItems(): void
    {
        $paginator = new Paginator(0, 4, 0);

        self::assertSame(0, $paginator->itemCountOnPage());
        self::assertSame([0, 0], [$paginator->firstItem(), $paginator->lastItem()]);
        self::assertSame([], $paginator->slice([]));
        self::assertTrue($paginator->isFirst());
        self::assertTrue($paginator->isLast());
    }

    public function testIndexIsKeptWithinThePages(): void
    {
        // E.g. kept when there were more items
        self::assertSame(2, (new Paginator(10, 4, 9))->index());
        self::assertSame(0, (new Paginator(10, 4, -1))->index());
        self::assertSame(0, (new Paginator(10, 4, 0))->at(-3)->index());
    }

    public function testMovesBetweenPages(): void
    {
        $first = new Paginator(10, 4, 0);

        self::assertTrue($first->isFirst());
        self::assertFalse($first->isLast());
        self::assertNull($first->previous());
        $last = $first->next()?->next();
        self::assertSame(2, $last?->index());
        self::assertTrue($last->isLast());
        self::assertNull($last->next());
        self::assertSame(1, $last->previous()?->index());
        self::assertSame(1, $first->at(1)->index());
    }

    public function testSlicesThePageOutOfTheItems(): void
    {
        self::assertSame(['e', 'f', 'g', 'h'], (new Paginator(10, 4, 1))->slice(range('a', end: 'j')));
    }

    public function testGivesTheVariablesOfATemplate(): void
    {
        self::assertSame(
            ['page' => 3, 'pageCount' => 3, 'itemCount' => 10, 'perPage' => 4, 'firstItem' => 9, 'lastItem' => 10],
            (new Paginator(10, 4, 2))->toArray(),
        );
    }

    public function testNeedsAPositiveSize(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one item per page, 0 given');

        new Paginator(10, 0, 0);
    }

    public function testNeedsItemsToCount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Item count must not be negative, -1 given');

        new Paginator(-1, 4, 0);
    }
}
