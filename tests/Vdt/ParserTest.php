<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt;

use MiniPaviService\Vdt\Parser;
use MiniPaviService\Vdt\Token;
use MiniPaviService\Vdt\TokenType;
use PHPUnit\Framework\TestCase;

final class ParserTest extends TestCase
{
    public function testCutsContentIntoTokens(): void
    {
        $tokens = (new Parser())->parse("\x0C\x1F\x41\x42AB\x12\x43\x1B\x41\x0E\x18\x08\x1E");

        self::assertSame(
            [
                [TokenType::Clear, "\x0C"],
                [TokenType::Position, "\x1F\x41\x42"],
                [TokenType::Glyph, 'A'],
                [TokenType::Glyph, 'B'],
                [TokenType::Repeat, "\x12\x43"],
                [TokenType::Attribute, "\x1B\x41"],
                [TokenType::Charset, "\x0E"],
                [TokenType::ClearLine, "\x18"],
                [TokenType::Move, "\x08"],
                [TokenType::Home, "\x1E"],
            ],
            array_map(fn(Token $token) => [$token->type, $token->source], $tokens),
        );
        self::assertSame([1, 2], [$tokens[1]->row, $tokens[1]->col]);
        self::assertSame(3, $tokens[4]->count);
        self::assertSame('fg', $tokens[5]->group);
    }

    public function testReadsDecimalPositions(): void
    {
        [$token] = (new Parser())->parse("\x1F0712");

        self::assertSame(TokenType::Position, $token->type);
        self::assertSame([7, 12], [$token->row, $token->col]);
    }

    public function testKeepsAG2AccentWithItsLetter(): void
    {
        $tokens = (new Parser())->parse("\x19\x42e!");

        self::assertCount(2, $tokens);
        self::assertSame(TokenType::Glyph, $tokens[0]->type);
        self::assertSame("\x19\x42e", $tokens[0]->bytes);
    }

    public function testKeepsProtocolSequencesWhole(): void
    {
        // PRO2 (ESC : x y) and PRO1 (ESC 9 x) are not attributes
        $tokens = (new Parser())->parse("\x1B\x3A\x69\x43\x1B\x39\x70A");

        self::assertSame([TokenType::Raw, TokenType::Raw, TokenType::Glyph], array_map(fn(Token $token) => $token->type, $tokens));
        self::assertSame("\x1B\x3A\x69\x43", $tokens[0]->source);
        self::assertSame("\x1B\x39\x70", $tokens[1]->source);
    }

    public function testToStringGivesBackTheContentAsRead(): void
    {
        $vdt = (string) file_get_contents(__DIR__ . '/../fixtures/vdt/background.vdt');

        self::assertSame($vdt, \MiniPaviService\Vdt\Vdt::fromString($vdt)->toString());
    }
}
