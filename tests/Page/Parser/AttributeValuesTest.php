<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Page\Parser;

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
}
