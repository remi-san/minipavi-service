<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt;

use MiniPaviService\Vdt\Player;
use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Cursor;
use MiniPaviService\Vdt\ScreenListener;
use MiniPaviService\Vdt\Token;
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

            public function sequence(Token $sequence, Cursor $cursor): void
            {
                $cursor->reset(10, col: 10);
            }
        };

        // Row 2, column 3, then two characters
        (new Player())->play(new Vdt("\x1F\x42\x43ab"), $listener);

        self::assertSame([[2, 3], [2, 4]], $listener->glyphs);
    }
}
