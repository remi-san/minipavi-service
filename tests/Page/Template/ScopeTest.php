<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Page\Template;

use MiniPaviService\InvalidValueException;
use MiniPaviService\Page\Parser\AttributeValues;
use MiniPaviService\Page\Template\Scope;
use PHPUnit\Framework\TestCase;

final class ScopeTest extends TestCase
{
    public function testSubstitutesPlaceholdersFollowingDots(): void
    {
        $scope = new Scope([
            'name' => 'Acme',
            'game' => ['title' => 'Chess', 'studio' => (object) ['city' => 'Paris']],
            'items' => new \ArrayObject(['first' => 'one']),
            'count' => 3,
        ]);

        self::assertSame(
            'Acme: Chess (Paris), 3 games, one',
            $scope->substitute('{{name}}: {{ game.title }} ({{game.studio.city}}), {{count}} games, {{items.first}}'),
        );
    }

    public function testBooleansAndNullBecomeText(): void
    {
        $scope = new Scope(['yes' => true, 'no' => false, 'none' => null]);

        self::assertSame('[1][][]', $scope->substitute('[{{yes}}][{{no}}][{{none}}]'));
    }

    public function testLeavesTextWithoutPlaceholdersAsItIs(): void
    {
        self::assertSame('{single} {{ }} {{1abc}}', (new Scope())->substitute('{single} {{ }} {{1abc}}'));
    }

    public function testUnknownVariableThrows(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('unknown variable {{game.year}}: no "year" in array');

        (new Scope(['game' => ['title' => 'Chess']]))->substitute('{{game.year}}');
    }

    public function testValueThatIsNotTextThrows(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('{{game}} is not text (array)');

        (new Scope(['game' => []]))->substitute('{{game}}');
    }

    public function testReadsPropertiesOfObjects(): void
    {
        $game = new class {
            public ?string $year = null;

            public string $title = 'Chess';

            public function __isset(string $name): bool
            {
                return $name === 'studio';
            }

            public function __get(string $name): string
            {
                return 'Acme';
            }
        };
        $scope = new Scope(['game' => $game]);

        // Declared (even null), or given by __isset() and __get()
        self::assertSame(['Chess', null, 'Acme'], [
            $scope->value('game.title'),
            $scope->value('game.year'),
            $scope->value('game.studio'),
        ]);
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('no "city" in class@anonymous');
        $scope->value('game.city');
    }

    public function testWithAddsVariablesAndChangesTheRowOffset(): void
    {
        $scope = (new Scope(['a' => 1, 'b' => 2]))->with(['b' => 3], rowOffset: 5);

        self::assertSame([1, 3], [$scope->value('a'), $scope->value('b')]);
        // Rows relative to row 5
        self::assertSame(6, $scope->rowOf(1));
        // Replaced, not added to: a nested <foreach> gives its item's row of the page
        self::assertSame(8, $scope->with([], rowOffset: 8)->rowOf(0));
    }

    public function testPlacesAnElementByItsRowInsideALoopOnly(): void
    {
        $attributes = new AttributeValues(['row' => '1', 'col' => '3']);

        self::assertSame($attributes, (new Scope())->placedByRow($attributes));
        self::assertSame(1, (new Scope())->rowOf(1));
        $inLoop = (new Scope())->with([], rowOffset: 5);
        self::assertSame(6, $inLoop->placedByRow($attributes)->int('row', default: 0));
        // Its first row by default
        self::assertSame(5, $inLoop->placedByRow(new AttributeValues([]))->int('row', default: 0));
    }
}
