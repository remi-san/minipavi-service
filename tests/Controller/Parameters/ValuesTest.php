<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Controller\Parameters;

use MiniPaviService\Controller\Parameters\Values;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Raw parameters from the context (possibly forged), read typed
 */
final class ValuesTest extends TestCase
{
    /**
     * Values a parameter may have (from the JSON of the context, possibly forged), and the
     * int read
     *
     * @return iterable<string, array{mixed, int}>
     */
    public static function intValues(): iterable
    {
        yield 'int' => [2, 2];
        yield 'negative int' => [-3, -3];
        yield 'integer text' => ['12', 12];
        yield 'integral float' => [3.0, 3];
        yield 'missing' => [null, 7];
        yield 'float' => [2.5, 7];
        yield 'huge float' => [1e20, 7];
        yield 'infinite float' => [INF, 7];
        yield 'integer text too large' => ['99999999999999999999', 7];
        yield 'other text' => ['2 pages', 7];
        yield 'empty text' => ['', 7];
        yield 'bool' => [true, 7];
        yield 'array' => [[1], 7];
    }

    #[DataProvider('intValues')]
    public function testIntCoercesOrFallsBackToTheDefault(mixed $value, int $expected): void
    {
        self::assertSame($expected, (new Values(['page' => $value]))->int('page', default: 7));
    }

    public function testIntDefaultsToZero(): void
    {
        self::assertSame(0, (new Values())->int('page'));
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function stringValues(): iterable
    {
        yield 'text' => ['abc', 'abc'];
        yield 'empty text' => ['', ''];
        yield 'int' => [12, '12'];
        yield 'float' => [2.5, '2.5'];
        yield 'missing' => [null, 'default'];
        yield 'bool' => [false, 'default'];
        yield 'array' => [['abc'], 'default'];
    }

    #[DataProvider('stringValues')]
    public function testStringCoercesOrFallsBackToTheDefault(mixed $value, string $expected): void
    {
        self::assertSame($expected, (new Values(['query' => $value]))->string('query', default: 'default'));
        self::assertSame($expected === 'default' ? '' : $expected, (new Values(['query' => $value]))->string('query'));
    }

    /**
     * @return iterable<string, array{mixed, ?bool}>
     */
    public static function boolValues(): iterable
    {
        yield 'true' => [true, true];
        yield 'false' => [false, false];
        yield '1' => [1, true];
        yield '0' => [0, false];
        yield 'text 1' => ['1', true];
        yield 'text true' => ['true', true];
        yield 'text false' => ['false', false];
        yield 'missing' => [null, null];
        yield 'other int' => [2, null];
        yield 'other text' => ['maybe', null];
        yield 'empty text' => ['', null];
        yield 'array' => [[true], null];
    }

    /**
     * @param ?bool $expected null: the default
     */
    #[DataProvider('boolValues')]
    public function testBoolCoercesOrFallsBackToTheDefault(mixed $value, ?bool $expected): void
    {
        $values = new Values(['logged' => $value]);

        self::assertSame($expected ?? true, $values->bool('logged', default: true));
        self::assertSame($expected ?? false, $values->bool('logged'));
    }

    public function testArrayGivesAnArrayOrTheDefault(): void
    {
        $values = new Values(['ids' => [1, 2], 'name' => 'abc']);

        self::assertSame([1, 2], $values->array('ids'));
        self::assertSame([], $values->array('name'));
        self::assertSame(['x'], $values->array('missing', ['x']));
    }
}
