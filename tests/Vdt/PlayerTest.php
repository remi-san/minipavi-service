<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt;

use MiniPaviService\Vdt\Player;
use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Cursor;
use MiniPaviService\Vdt\Screen\Zone;
use MiniPaviService\Vdt\ScreenListener;
use MiniPaviService\Vdt\Token;
use MiniPaviService\Vdt\TokenType;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\TestCase;

final class PlayerTest extends TestCase
{
    public function testListenerChangingTheCursorItIsGivenDoesNotMoveWhatFollows(): void
    {
        $listener = new class implements ScreenListener {
            /** @var list<array{int, int}> */
            public array $glyphs = [];

            public function glyph(int $row, int $col, string $bytes, int $width, Attributes $attributes): void
            {
                $this->glyphs[] = [$row, $col];
            }

            public function delimiter(int $row, int $col, int $width, Attributes $attributes, Zone $zone): void {}

            public function sequence(Token $sequence, Cursor $cursor): void
            {
                $cursor->reset(10, col: 10);
            }
        };

        // Row 2, column 3, then two characters
        (new Player())->play(new Vdt("\x1F\x42\x43ab"), $listener);

        self::assertSame([[2, 3], [2, 4]], $listener->glyphs);
    }

    public function testRepetitionWithoutCharacterIsNeitherDrawnNorASequence(): void
    {
        $listener = new class implements ScreenListener {
            /** @var list<TokenType> */
            public array $tokens = [];

            public int $glyphs = 0;

            public function glyph(int $row, int $col, string $bytes, int $width, Attributes $attributes): void
            {
                $this->glyphs++;
            }

            public function delimiter(int $row, int $col, int $width, Attributes $attributes, Zone $zone): void
            {
                $this->glyphs++;
            }

            public function sequence(Token $sequence, Cursor $cursor): void
            {
                $this->tokens[] = $sequence->type;
            }
        };

        // REP 3 with nothing sent before, then a clear
        (new Player())->play(new Vdt("\x12\x43\x0C"), $listener);

        self::assertSame(0, $listener->glyphs);
        self::assertSame([TokenType::Clear], $listener->tokens);
    }
}
