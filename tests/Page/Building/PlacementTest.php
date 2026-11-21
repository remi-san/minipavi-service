<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Page\Building;

use MiniPaviService\Page\Building\AttributeValues;
use MiniPaviService\Page\Building\PageXmlException;
use MiniPaviService\Page\Building\Placement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlacementTest extends TestCase
{
    public function testOnThePageRowIsTheRowOfThePage(): void
    {
        $attributes = new AttributeValues(['row' => '3', 'col' => '4']);

        self::assertSame($attributes, Placement::page()->placeByRow($attributes));
    }

    public function testInAnItemRowIsRelativeToItsFirstRow(): void
    {
        $placed = Placement::loopItem(firstRow: 5)->placeByRow(new AttributeValues(['row' => '1', 'col' => '3']));

        self::assertSame([6, 3], [$placed->int('row', default: 0), $placed->int('col', default: 0)]);
        // Its first row by default
        self::assertSame(5, Placement::loopItem(firstRow: 5)->placeByRow(new AttributeValues([]))->int(
            'row',
            default: 0,
        ));
    }

    public function testInALoopWhenThePageIsReadRowIsLeftForTheItems(): void
    {
        // The rows of its items aren't known yet: row is checked when they are drawn
        $placed = Placement::loop()->placeByRow(new AttributeValues(['row' => 'x', 'col' => '3']));

        self::assertSame([false, '3'], [$placed->has('row'), $placed->string('col')]);
    }

    public function testThePageAcceptsWhatRefusesALoop(): void
    {
        $this->expectNotToPerformAssertions();

        Placement::page()->refuseInLoop();
    }

    /**
     * @return iterable<string, array{Placement}>
     */
    public static function inLoop(): iterable
    {
        yield 'when the page is read' => [Placement::loop()];
        yield 'when an item is drawn' => [Placement::loopItem(firstRow: 5)];
    }

    #[DataProvider('inLoop')]
    public function testALoopIsRefused(Placement $placement): void
    {
        $this->expectException(PageXmlException::class);
        $this->expectExceptionMessage('not inside <foreach>');

        $placement->refuseInLoop();
    }
}
