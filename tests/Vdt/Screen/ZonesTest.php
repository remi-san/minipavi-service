<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt\Screen;

use MiniPaviService\Minitel\ScreenSize;
use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Zone;
use MiniPaviService\Vdt\Screen\Zones;
use PHPUnit\Framework\TestCase;

final class ZonesTest extends TestCase
{
    public function testATextCellIsInTheZoneOfTheLastDelimiterBeforeIt(): void
    {
        $zones = new Zones();
        $zones->text(row: 3, col: 2, width: 1, delimiter: self::underlined());
        $zones->text(row: 3, col: 3, width: 2);

        self::assertTrue($zones->isDelimiter(row: 3, col: 2));
        self::assertFalse($zones->of(row: 3, col: 2)->isUnderlined());
        self::assertFalse($zones->before(row: 3, col: 2)->isUnderlined());
        self::assertTrue($zones->of(row: 3, col: 4)->isUnderlined());
        // Its zone runs to the end of the row's text cells
        self::assertTrue($zones->before(row: 3, col: 5)->isUnderlined());
        self::assertFalse($zones->before(row: 3, col: 6)->isUnderlined());
        self::assertFalse($zones->of(row: 4, col: 4)->isUnderlined());
    }

    public function testAMosaicOrEmptyCellEndsTheZone(): void
    {
        $zones = new Zones();
        $zones->text(row: 3, col: 1, width: 4, delimiter: null);
        $zones->text(row: 3, col: 1, width: 1, delimiter: self::underlined());
        $zones->mosaic(row: 3, col: 3);

        self::assertTrue($zones->before(row: 3, col: 3)->isUnderlined());
        self::assertFalse($zones->before(row: 3, col: 4)->isUnderlined());
    }

    public function testADelimiterDrawnOverIsATextCellAgain(): void
    {
        $zones = new Zones();
        $zones->text(row: 3, col: 1, width: 1, delimiter: self::underlined());
        $zones->text(row: 3, col: 1, width: 2);

        self::assertFalse($zones->isDelimiter(row: 3, col: 1));
        self::assertFalse($zones->before(row: 3, col: 2)->isUnderlined());
    }

    public function testClearKeepsTheStatusRow(): void
    {
        $zones = new Zones();
        foreach ([0, 1, ScreenSize::ROWS] as $row) {
            $zones->text($row, col: 1, width: 2, delimiter: self::underlined());
        }
        $zones->clear();

        self::assertTrue($zones->before(row: 0, col: 3)->isUnderlined());
        self::assertFalse($zones->before(row: 1, col: 3)->isUnderlined());
        self::assertFalse($zones->before(row: ScreenSize::ROWS, col: 3)->isUnderlined());
    }

    private static function underlined(): Zone
    {
        return Zone::none()->with(Attributes::UNDERLINE, Attributes::defaults())->delimited();
    }
}
