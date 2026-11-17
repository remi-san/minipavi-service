<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Page\Template;

use MiniPaviService\Page\Template\Scope;
use PHPUnit\Framework\TestCase;

final class ScopeTest extends TestCase
{
    public function testSubstitutesPlaceholdersFollowingDots(): void
    {
        $scope = new Scope([
            'name' => 'Octoblue',
            'game' => ['title' => 'Alien', 'studio' => (object) ['city' => 'Paris']],
            'items' => new \ArrayObject(['first' => 'one']),
            'count' => 3,
        ]);

        self::assertSame(
            'Octoblue: Alien (Paris), 3 games, one',
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
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('unknown variable {{game.year}}: no "year" in array');

        (new Scope(['game' => ['title' => 'Alien']]))->substitute('{{game.year}}');
    }

    public function testValueThatIsNotTextThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('{{game}} is not text (array)');

        (new Scope(['game' => []]))->substitute('{{game}}');
    }

    public function testReadsPropertiesOfObjects(): void
    {
        $game = new class {
            public ?string $year = null;

            public string $title = 'Alien';

            public function __isset(string $name): bool
            {
                return $name === 'studio';
            }

            public function __get(string $name): string
            {
                return 'Octoblue';
            }
        };
        $scope = new Scope(['game' => $game]);

        // Declared (even null), or given by __isset() and __get()
        self::assertSame(['Alien', null, 'Octoblue'], [
            $scope->value('game.title'),
            $scope->value('game.year'),
            $scope->value('game.studio'),
        ]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('no "city" in class@anonymous');
        $scope->value('game.city');
    }

    public function testWithAddsVariablesAndChangesTheRowOffset(): void
    {
        $scope = (new Scope(['a' => 1, 'b' => 2]))->with(['b' => 3], 5);

        self::assertSame([1, 3], [$scope->value('a'), $scope->value('b')]);
        self::assertSame(5, $scope->rowOffset);
    }
}
