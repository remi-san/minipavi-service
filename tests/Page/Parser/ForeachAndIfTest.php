<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Page\Parser;

use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Tests\Support\ParsedPages;
use MiniPaviService\Tests\Support\ScreenRows;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What a template draws with <foreach> and <if>, and with the data it is given
 */
final class ForeachAndIfTest extends TestCase
{
    private ParsedPages $pages;

    protected function setUp(): void
    {
        $this->pages = new ParsedPages();
    }

    public function testKeyCanBeDrawnForEachItem(): void
    {
        $page = $this->pages->parse(<<<'XML'
            <page type="template">
              <foreach items="keys" as="key" row="3"><key col="2">{{key}}</key></foreach>
            </page>
            XML);

        self::assertSame(
            [' 3|   Envoi' . str_repeat(' ', times: 32) . '|', ' 4|   Suite' . str_repeat(' ', times: 32) . '|'],
            ScreenRows::drawn($page->display(new Terminal(), ['keys' => ['Envoi', 'Suite']])),
        );
    }

    public function testForeachStartsAtTheFirstRowOrItsItemsAndGoesDownARowPerItem(): void
    {
        $page = $this->pages->parse(<<<'XML'
            <page type="template">
              <foreach items="rows" as="row" step="2">
                <text>{{row.name}}</text>
                <foreach items="row.cells" as="cell"><text col="5">{{cell}}</text></foreach>
              </foreach>
            </page>
            XML);

        // Without row: row 1 of the page, the first row of the item inside a <foreach>
        self::assertSame(
            [
                ' 1|A   a1                                  |',
                ' 2|    a2                                  |',
                ' 3|B   b1                                  |',
            ],
            ScreenRows::drawn($page->display(new Terminal(), [
                'rows' => [['name' => 'A', 'cells' => ['a1', 'a2']], ['name' => 'B', 'cells' => ['b1']]],
            ])),
        );
    }

    /**
     * @return iterable<string, array{iterable<mixed>}>
     */
    public static function keyedItems(): iterable
    {
        yield 'an array with keys' => [['pirates' => 'Harbour Pirates', 'rocket' => 'Rocket Rescue']];
        yield 'an iterator with keys' => [new \ArrayIterator(['pirates' => 'Harbour Pirates', 'rocket' => 'Rescue'])];
        // The same key twice: both items kept
        yield 'a generator repeating a key' => [(static function (): \Generator {
            yield 'game' => 'Harbour Pirates';
            yield 'game' => 'Rocket Rescue';
        })()];
    }

    /**
     * The items in their order, whatever their keys: numbered from 1, one row each
     *
     * @param iterable<mixed> $games
     */
    #[DataProvider('keyedItems')]
    public function testForeachTakesItemsInTheirOrderWhateverTheirKeys(iterable $games): void
    {
        $page = $this->pages->parse(<<<'XML'
            <page type="template">
              <foreach items="games" as="game" row="3">
                <text>{{loop.index}}/{{loop.count}}</text>
                <if test="loop.first"><text col="6">first</text></if>
                <if test="loop.last"><text col="6">last</text></if>
              </foreach>
            </page>
            XML);

        self::assertSame(
            [' 3|' . str_pad('1/2  first', length: 40) . '|', ' 4|' . str_pad('2/2  last', length: 40) . '|'],
            ScreenRows::drawn($page->display(new Terminal(), ['games' => $games])),
        );
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function conditions(): iterable
    {
        yield 'null' => [null, 'false'];
        yield 'false' => [false, 'false'];
        yield 'true' => [true, 'true'];
        yield 'zero' => [0, 'false'];
        yield 'one' => [1, 'true'];
        yield 'a negative number' => [-1, 'true'];
        yield 'zero as a float' => [0.0, 'false'];
        yield 'one as a float' => [1.0, 'true'];
        yield 'an empty string' => ['', 'false'];
        yield 'the string 0' => ['0', 'false'];
        yield 'a space' => [' ', 'true'];
        yield 'an empty array' => [[], 'false'];
        yield 'an array' => [[0], 'true'];
        yield 'an object' => [new \stdClass(), 'true'];
    }

    /**
     * PHP's truthiness
     *
     * @param string $shown the text of the <if> shown: true or false
     */
    #[DataProvider('conditions')]
    public function testConditionIsTrueAsInPhp(mixed $value, string $shown): void
    {
        $page = $this->pages->parse(<<<'XML'
            <page type="template">
              <if test="value"><text row="1">true</text></if>
              <if test="!value"><text row="1">false</text></if>
            </page>
            XML);

        self::assertSame([' 1|' . str_pad($shown, length: 40) . '|'], ScreenRows::drawn($page->display(new Terminal(), [
            'value' => $value,
        ])));
    }

    public function testForeachItemNameIsTrimmed(): void
    {
        $page = $this->pages->parse(<<<'XML'
            <page type="template">
              <foreach items="games" as=" game " row="2">
                <text>{{game}}</text>
              </foreach>
            </page>
            XML);

        self::assertSame(
            [' 2|Chess                                   |', ' 3|Tetris                                  |'],
            ScreenRows::drawn($page->display(new Terminal(), ['games' => ['Chess', 'Tetris']])),
        );
    }

    public function testTemplateRepeatsAndTestsItsContent(): void
    {
        $page = $this->pages->parse(<<<'XML'
            <page type="template">
              <text row="1">{{title}} ({{games}})</text>
              <foreach items="items" as="game" row="3" step="2">
                <text col="3">{{loop.index}}/{{loop.count}} {{game.name}}</text>
                <if test="!loop.last"><line row="1" col="3" length="4"/></if>
                <if test="game.new"><text col="22">new</text></if>
              </foreach>
            </page>
            XML);

        self::assertSame(
            [
                ' 1|Games (2)                               |',
                ' 3|  1/2 Harbour Pirates                   |',
                ' 4|  ────                                  |',
                ' 5|  2/2 Rocket Rescue  new                |',
            ],
            ScreenRows::drawn($page->display(new Terminal(), [
                'title' => 'Games',
                'games' => 2,
                'items' => [
                    ['name' => 'Harbour Pirates', 'new' => false],
                    (object) ['name' => 'Rocket Rescue', 'new' => true],
                ],
            ])),
        );
    }

    public function testNestedForeachRowsAreRelativeToTheEnclosingItem(): void
    {
        $page = $this->pages->parse(<<<'XML'
            <page type="template">
              <foreach items="rows" as="row" row="2" step="3">
                <text>{{row.name}}</text>
                <foreach items="row.cells" as="cell" row="1">
                  <text col="3">{{cell}}</text>
                </foreach>
              </foreach>
            </page>
            XML);

        self::assertSame(
            [
                ' 2|A                                       |',
                ' 3|  a1                                    |',
                ' 4|  a2                                    |',
                ' 5|B                                       |',
                ' 6|  b1                                    |',
            ],
            ScreenRows::drawn($page->display(new Terminal(), [
                'rows' => [['name' => 'A', 'cells' => ['a1', 'a2']], ['name' => 'B', 'cells' => ['b1']]],
            ])),
        );
    }

    public function testTemplateImageCanComeFromTheData(): void
    {
        $page = $this->pages->parse('<page type="template"><image row="3">{{logo}}</image></page>');

        self::assertSame(
            [' 3|##                                      |', ' 4|ok                                      |'],
            ScreenRows::drawn($page->display(new Terminal(), ['logo' => 'images/logo.vdt'])),
        );
    }

    /**
     * @return iterable<string, array{\Countable, list<string>}>
     */
    public static function collections(): iterable
    {
        yield 'empty' => [new \ArrayObject(), [' 1|No game                                 |']];
        yield 'with elements' => [
            new \ArrayObject(['Rocket Rescue']),
            [' 1|Games                                   |'],
        ];
    }

    /**
     * A collection object is true when it has elements, as an array
     *
     * @param list<string> $rows
     */
    #[DataProvider('collections')]
    public function testConditionOnACollectionTestsItsElements(\Countable $games, array $rows): void
    {
        $page = $this->pages->parse(<<<'XML'
            <page type="template">
              <if test="games"><text row="1">Games</text></if>
              <if test="!games"><text row="1">No game</text></if>
            </page>
            XML);

        self::assertSame($rows, ScreenRows::drawn($page->display(new Terminal(), ['games' => $games])));
    }

    public function testTemplateRowInAForeachIsCheckedWhenDisplayed(): void
    {
        // Relative to the item: row 0 is the item's first row
        $page = $this->pages->parse(
            '<page type="template"><foreach items="xs" as="x" row="5"><image row="0">images/logo.vdt</image></foreach></page>',
            name: 'f.xml',
        );

        self::assertSame(
            [' 5|##                                      |', ' 6|ok                                      |'],
            ScreenRows::drawn($page->display(new Terminal(), ['xs' => [1]])),
        );
    }

    public function testBackgroundInAForeachIsAtItsOwnPlace(): void
    {
        $page = $this->pages->parse(
            '<page type="template"><foreach items="xs" as="x" row="5"><background>images/logo.vdt</background></foreach></page>',
        );

        // Not moved to the item's row: its content positions itself
        self::assertSame(
            [' 1|##                                      |', ' 2|ok                                      |'],
            ScreenRows::drawn($page->display(new Terminal(), ['xs' => [1]])),
        );
    }
}
