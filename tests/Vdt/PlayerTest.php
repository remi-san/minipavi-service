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

            public function clearLine(int $row, int $col, Attributes $attributes, ?Zone $delimiter): void {}

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

            public function clearLine(int $row, int $col, Attributes $attributes, ?Zone $delimiter): void {}

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

    public function testAMosaicCharacterDoesNotUseUpAWaitingMask(): void
    {
        // ESC X in mosaic, a mosaic space and character, SI, then a space: the emulator only
        // makes a text space a delimiter (print()), so the mask still waits for it
        $delimiters = self::delimiters(new Vdt("\x0E\x1B\x58 \x7F\x0F A"));

        self::assertSame([[1, 3]], $delimiters);
    }

    public function testClearLineDrawsADelimiterWhileAZoneAttributeWaits(): void
    {
        // ESC Z then CAN at column 2: its first space is a delimiter (the emulator prints
        // CAN's spaces), which uses up what waits, so the next space is not one
        $delimiters = self::delimiters(new Vdt("A\x1B\x5A\x18\x09B C"));

        self::assertSame([[1, 2]], $delimiters);
    }

    /**
     * Where the content draws delimiters, CAN's included
     *
     * @return list<array{int, int}> row and column of each
     */
    private static function delimiters(Vdt $vdt): array
    {
        $listener = new class implements ScreenListener {
            /** @var list<array{int, int}> */
            public array $delimiters = [];

            public function glyph(int $row, int $col, string $bytes, int $width, Attributes $attributes): void {}

            public function delimiter(int $row, int $col, int $width, Attributes $attributes, Zone $zone): void
            {
                $this->delimiters[] = [$row, $col];
            }

            public function clearLine(int $row, int $col, Attributes $attributes, ?Zone $delimiter): void
            {
                if ($delimiter !== null) {
                    $this->delimiters[] = [$row, $col];
                }
            }

            public function sequence(Token $sequence, Cursor $cursor): void {}
        };
        (new Player())->play($vdt, $listener);

        return $listener->delimiters;
    }
}
