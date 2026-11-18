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
        yield 'off the screen' => ['{"row": 30, "col": 1, "length": 1, "lines": 1}'];
    }

    #[DataProvider('unreadable')]
    public function testWhatItCantReadIsNoCommand(string $json): void
    {
        self::assertNull((new InputCommandJson())->decode($json));
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
}
