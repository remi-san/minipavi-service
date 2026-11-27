<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Text;

use MiniPaviService\InvalidValueException;
use MiniPaviService\Tests\Support\ExhaustedBacktrackLimit;
use MiniPaviService\Text\Placeholders;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlaceholdersTest extends TestCase
{
    public function testReplacesVariables(): void
    {
        $placeholders = new Placeholders(['name' => 'Ada', 'count' => 3, 'ratio' => 1.5]);

        self::assertSame('Ada: 3 (1.5)', $placeholders->substitute('{{name}}: {{ count }} ({{ratio}})'));
    }

    public function testFollowsDottedPathsThroughArraysAndObjects(): void
    {
        $studio = new class {
            public string $city = 'Lyon';
        };
        $placeholders = new Placeholders(['game' => ['name' => 'Rocket', 'studio' => $studio]]);

        self::assertSame('Rocket, Lyon', $placeholders->substitute('{{game.name}}, {{game.studio.city}}'));
    }

    public function testReadsArrayAccess(): void
    {
        $placeholders = new Placeholders(['user' => new \ArrayObject(['name' => 'Ada'])]);

        self::assertSame('Ada', $placeholders->substitute('{{user.name}}'));
    }

    public function testReadsMagicProperties(): void
    {
        $user = new class {
            public function __isset(string $name): bool
            {
                return $name === 'name';
            }

            public function __get(string $name): string
            {
                return 'Ada';
            }
        };

        self::assertSame('Ada', (new Placeholders(['user' => $user]))->substitute('{{user.name}}'));
    }

    public function testDoesNotReadPrivateProperties(): void
    {
        $user = new class {
            private string $email = 'ada@example.com';

            public function email(): string
            {
                return $this->email;
            }
        };

        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('unknown variable {{user.email}}: no "email" in class@anonymous');

        (new Placeholders(['user' => $user]))->substitute('{{user.email}}');
    }

    public function testBooleansAreOneOrNothingAndNullIsNothing(): void
    {
        $placeholders = new Placeholders(['yes' => true, 'no' => false, 'none' => null]);

        self::assertSame('[1][][]', $placeholders->substitute('[{{yes}}][{{no}}][{{none}}]'));
    }

    public function testStringableIsText(): void
    {
        $name = new class implements \Stringable {
            public function __toString(): string
            {
                return 'Ada';
            }
        };

        self::assertSame('Ada', (new Placeholders(['name' => $name]))->substitute('{{name}}'));
    }

    public function testAValueThatIsNotTextIsAnError(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('{{games}} is not text (array)');

        (new Placeholders(['games' => ['a', 'b']]))->substitute('{{games}}');
    }

    public function testAFailureOfTheRegularExpressionIsAnError(): void
    {
        $limit = ExhaustedBacktrackLimit::exhaust();
        try {
            (new Placeholders(['name' => 'Ada']))->substitute('{{name}}');
            self::fail('The placeholders were replaced');
        } catch (InvalidValueException $e) {
            self::assertSame('cannot replace the placeholders: Backtrack limit exhausted', $e->getMessage());
        } finally {
            $limit->restore();
        }
    }

    public function testAnUnknownVariableIsAnError(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('unknown variable {{user}}');

        (new Placeholders())->substitute('{{user}}');
    }

    public function testAnUnknownMemberIsAnError(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('unknown variable {{game.year}}: no "year" in array');

        (new Placeholders(['game' => ['name' => 'Rocket']]))->value('game.year');
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function texts(): iterable
    {
        yield 'placeholders only' => ['{{name}} and {{ game.name }}', null];
        yield 'no braces' => ['plain text', null];
        yield 'a number' => ['a {{1}} b', '{{1}}'];
        yield 'a dash' => ['{{user-name}}', '{{user-name}}'];
        yield 'a trailing dot' => ['{{name}} {{user.}}', '{{user.}}'];
        yield 'empty' => ['{{}}', '{{}}'];
    }

    #[DataProvider('texts')]
    public function testCheckNamesTheFirstMalformedPlaceholder(string $text, ?string $malformed): void
    {
        try {
            Placeholders::check($text);
        } catch (InvalidValueException $e) {
            self::assertSame("{$malformed} is not a placeholder", $e->getMessage());

            return;
        }
        self::assertNull($malformed, 'A malformed placeholder was accepted');
    }

    public function testLeavesMalformedPlaceholdersAsTheyAre(): void
    {
        self::assertSame('{{user-name}}', (new Placeholders(['user' => 'Ada']))->substitute('{{user-name}}'));
    }
}
