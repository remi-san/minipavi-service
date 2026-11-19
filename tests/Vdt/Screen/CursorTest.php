<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt\Screen;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Minitel\ScreenSize;
use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Cursor;
use MiniPaviService\Vdt\Screen\Zone;
use PHPUnit\Framework\TestCase;

final class CursorTest extends TestCase
{
    public function testStartsAtRow1Column1WithTheDefaultAttributes(): void
    {
        $cursor = new Cursor();

        self::assertSame([1, 1], [$cursor->row(), $cursor->col()]);
        self::assertEquals(Attributes::defaults(), $cursor->attributes());
    }

    public function testSetsAttributesAndTheCharset(): void
    {
        $cursor = new Cursor();
        $cursor->reset(5, col: 1);
        // SO first: it would turn off what is set before it (separation, here, in mosaic)
        $cursor->setCharset("\x0E");
        $cursor->setAttribute(Attributes::UNDERLINE);
        $cursor->setAttribute("\x41");

        self::assertTrue($cursor->attributes()->isUnderlined());
        self::assertSame(Color::Red, $cursor->attributes()->foreground());
        self::assertTrue($cursor->attributes()->isMosaic());
    }

    public function testDiscardsDoubleHeightOnRows0And1(): void
    {
        foreach ([0, 1] as $row) {
            $cursor = new Cursor();
            $cursor->reset($row, col: 1);
            $cursor->setAttribute(Attributes::DOUBLE_HEIGHT);
            $cursor->setAttribute(Attributes::DOUBLE);

            self::assertFalse($cursor->attributes()->isDoubleHeight(), "row {$row}");
        }
        $cursor = new Cursor();
        $cursor->reset(2, col: 1);
        $cursor->setAttribute(Attributes::DOUBLE_HEIGHT);
        self::assertTrue($cursor->attributes()->isDoubleHeight());
    }

    public function testDiscardsSizesAndInverseInMosaic(): void
    {
        // As the Minitel does: in mosaic, ESC L, M, N, O and ESC ] are discarded (MiniPavi's
        // emulator, setSize() and setInvert()), staying normal size and not inverse, as SO
        // sets them
        foreach ([
            Attributes::DOUBLE_HEIGHT,
            Attributes::DOUBLE_WIDTH,
            Attributes::DOUBLE,
            Attributes::INVERSE,
        ] as $byte) {
            $cursor = new Cursor();
            $cursor->reset(10, col: 1);
            $cursor->setCharset("\x0E");
            $cursor->setAttribute($byte);

            self::assertEquals(Attributes::defaults()->withCharset("\x0E"), $cursor->attributes());
        }
    }

    public function testResetGoesBackToTheDefaultAttributes(): void
    {
        $cursor = new Cursor();
        $cursor->setAttribute(Attributes::INVERSE);
        $cursor->reset(3, col: 7);

        self::assertSame([3, 7], [$cursor->row(), $cursor->col()]);
        self::assertEquals(Attributes::defaults(), $cursor->attributes());
    }

    public function testAdvanceWrapsToTheNextRow(): void
    {
        $cursor = new Cursor();
        $cursor->reset(3, col: ScreenSize::COLS);
        $cursor->advance(1);

        self::assertSame([4, 1], [$cursor->row(), $cursor->col()]);
    }

    public function testUnderlineInTextWaitsForADelimiter(): void
    {
        $cursor = new Cursor();
        $cursor->reset(5, col: 1);
        $cursor->setAttribute(Attributes::UNDERLINE);

        // Not the next character's: the zone's that the next space starts
        self::assertEquals(Attributes::defaults(), $cursor->attributes());
        self::assertFalse($cursor->delimits('A'));
        self::assertTrue($cursor->delimits(' '));
        $cursor->delimit();
        self::assertTrue($cursor->zone()->isUnderlined());
        self::assertFalse($cursor->delimits(' '));
    }

    public function testNoDelimiterInMosaic(): void
    {
        $cursor = new Cursor();
        $cursor->reset(5, col: 1);
        $cursor->setCharset("\x0E");
        // Masking waits, in mosaic too
        $cursor->setAttribute(Attributes::MASK);

        self::assertFalse($cursor->delimits(' '));
    }

    public function testPositioningDropsTheZone(): void
    {
        $cursor = new Cursor();
        $cursor->setAttribute(Attributes::UNDERLINE);
        $cursor->delimit();
        $cursor->setAttribute(Attributes::UNDERLINE);
        $cursor->reset(5, col: 1);

        self::assertEquals(Zone::none(), $cursor->zone());
    }
}
