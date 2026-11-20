<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Page\Parser;

use MiniPaviService\InvalidValueException;
use MiniPaviService\Page\Parser\AttributeValues;
use MiniPaviService\Page\Parser\PageXmlException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AttributeValuesTest extends TestCase
{
    public function testARequiredValueIsTrimmed(): void
    {
        self::assertSame('games', (new AttributeValues(['items' => ' games ']))->required('items'));
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function missing(): iterable
    {
        yield 'absent' => [[]];
        yield 'empty' => [['items' => '']];
        yield 'blank' => [['items' => '  ']];
    }

    /**
     * @param array<string, string> $values
     */
    #[DataProvider('missing')]
    public function testARequiredValueMissingIsAnError(array $values): void
    {
        $this->expectException(PageXmlException::class);
        $this->expectExceptionMessage('items is missing');

        (new AttributeValues($values))->required('items');
    }

    /**
     * For a service's element (ElementBuilder): an attribute given empty is given
     */
    public function testTellsWhetherAnAttributeIsGiven(): void
    {
        $values = new AttributeValues(['note' => '', 'count' => '2']);

        self::assertTrue($values->has('note'));
        self::assertTrue($values->has('count'));
        self::assertFalse($values->has('end'));
    }

    public function testAPositionIsAtRowAndCol(): void
    {
        $position = (new AttributeValues(['row' => '3', 'col' => '7']))->position();

        self::assertSame([3, 7], [$position->row, $position->col]);
    }

    public function testAPositionWithoutRowOrColIsAtItsDefaultRowAndTheFirstColumn(): void
    {
        $top = (new AttributeValues([]))->position();
        $input = (new AttributeValues(['col' => '5']))->position(defaultRow: 13);

        self::assertSame([1, 1], [$top->row, $top->col]);
        self::assertSame([13, 5], [$input->row, $input->col]);
    }

    public function testAPositionOffTheScreenIsAnError(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('Column 41 is off the screen (1 to 40)');

        (new AttributeValues(['col' => '41']))->position();
    }
}
