<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Page\Building;

use MiniPaviService\Page\Building\Rows;
use PHPUnit\Framework\TestCase;

final class RowsTest extends TestCase
{
    public function testRowsOfThePageAreThemselves(): void
    {
        self::assertSame([0, 3], [(new Rows())->rowOf(0), (new Rows())->rowOf(3)]);
    }

    public function testRowsOfAnItemAreRelativeToItsFirstRow(): void
    {
        // 0 = the item's first row
        self::assertSame([5, 8], [(new Rows(first: 5))->rowOf(0), (new Rows(first: 5))->rowOf(3)]);
    }
}
