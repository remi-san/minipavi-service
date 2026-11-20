<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Client;

use MiniPaviService\Client\InputCommandJson;
use MiniPaviService\Gateway\InputCommand;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\InputField;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\Span;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InputCommandJsonTest extends TestCase
{
    public function testReadsBackTheCommandItWrote(): void
    {
        $json = new InputCommandJson();
        $command = new InputCommand(
            new InputField(
                new Span(new Position(20, col: 5), length: 10),
                lines: 3,
                validWith: [FunctionKey::Send, FunctionKey::Next],
                cursor: false,
                spaceChar: ' ',
            ),
            ['first', 'second'],
        );

        self::assertEquals($command, $json->decode($json->encode($command)));
    }

    public function testWritesTextThatIsntUtf8(): void
    {
        $json = new InputCommandJson();
        $command = new InputCommand(new InputField(new Span(new Position(2), length: 5)), ["caf\xE9"]);

        // Not lost: what can't be UTF-8 is replaced
        self::assertSame(["caf\u{FFFD}"], $json->decode($json->encode($command))?->prefill);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unreadable(): iterable
    {
        yield 'nothing' => [''];
        yield 'not JSON' => ['{row'];
        yield 'not an object' => ['"command"'];
        yield 'a position missing' => ['{"row": 1, "col": 1, "length": 1}'];
        yield 'a position not a number' => ['{"row": "1", "col": 1, "length": 1, "lines": 1}'];
        // Refused by InputField, Span and Position
        yield 'below the last row' => ['{"row": 30, "col": 1, "length": 1, "lines": 1}'];
        yield 'more lines than rows left' => ['{"row": 24, "col": 1, "length": 1, "lines": 2}'];
        yield 'no line' => ['{"row": 2, "col": 1, "length": 1, "lines": 0}'];
        yield 'column 0' => ['{"row": 2, "col": 0, "length": 1, "lines": 1}'];
        yield 'past the last column' => ['{"row": 2, "col": 41, "length": 1, "lines": 1}'];
        yield 'a length past the last column' => ['{"row": 2, "col": 38, "length": 4, "lines": 1}'];
    }

    #[DataProvider('unreadable')]
    public function testWhatItCantReadIsNoCommand(string $json): void
    {
        self::assertNull((new InputCommandJson())->decode($json));
    }

    public function testWhatIsMissingHasTheFieldsDefaults(): void
    {
        $command = (new InputCommandJson())->decode('{"row": 2, "col": 3, "length": 4, "lines": 1}');

        self::assertEquals(new InputCommand(new InputField(new Span(new Position(2, col: 3), length: 4))), $command);
    }

    public function testWithoutAKnownKeyTheFieldIsValidatedWithSend(): void
    {
        // Rather than no key at all, which would disable every key of the page
        $command = (new InputCommandJson())->decode(
            '{"row": 2, "col": 3, "length": 4, "lines": 1, "validWith": ["NOPE"], "spaceChar": 7}',
        );

        self::assertSame([FunctionKey::Send], $command?->field->validWith);
        self::assertSame('.', $command?->field->spaceChar);
    }

    public function testIgnoresUnknownKeysAndWrongLines(): void
    {
        $command = (new InputCommandJson())->decode(
            '{"row": 2, "col": 3, "length": 4, "lines": 1, "validWith": ["ENVOI", "NOPE", 3], "prefill": ["a", 1]}',
        );

        self::assertNotNull($command);
        self::assertSame([FunctionKey::Send], $command->field->validWith);
        self::assertSame(['a', ''], $command->prefill);
    }

    public function testKeepsTheKnownKeysAfterAnUnknownOne(): void
    {
        $command = (new InputCommandJson())->decode(
            '{"row": 2, "col": 3, "length": 4, "lines": 1, "validWith": ["BOGUS", "ENVOI", "SUITE"]}',
        );

        self::assertSame([FunctionKey::Send, FunctionKey::Next], $command?->field->validWith);
    }

    public function testReadsAPrefillGivenAsAnObjectAsItsLines(): void
    {
        $command = (new InputCommandJson())->decode(
            '{"row": 2, "col": 3, "length": 4, "lines": 2, "prefill": {"first": "a", "second": "b"}}',
        );

        self::assertSame(['a', 'b'], $command?->prefill);
    }
}
