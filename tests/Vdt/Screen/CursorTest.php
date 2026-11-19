<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt\Screen;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Minitel\ScreenSize;
use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Cursor;
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
        $cursor->setAttribute(Attributes::INVERSE);
        $cursor->setAttribute("\x41");
        $cursor->setCharset("\x0E");

        self::assertTrue($cursor->attributes()->isInverse());
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

    public function testDiscardsSizesInMosaic(): void
    {
        // As the Minitel does: in mosaic, ESC L, M, N and O are discarded (MiniPavi's
        // emulator, setSize()), the size staying normal, as SO sets it
        foreach ([Attributes::DOUBLE_HEIGHT, Attributes::DOUBLE_WIDTH, Attributes::DOUBLE] as $size) {
            $cursor = new Cursor();
            $cursor->reset(10, col: 1);
            $cursor->setCharset("\x0E");
            $cursor->setAttribute($size);

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
}
