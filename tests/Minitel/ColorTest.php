<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Minitel;

use MiniPaviService\InvalidValueException;
use MiniPaviService\Minitel\Color;
use PHPUnit\Framework\TestCase;

final class ColorTest extends TestCase
{
    public function testEachColorIsItsIndex(): void
    {
        foreach (Color::cases() as $color) {
            self::assertSame($color, Color::fromIndex($color->index()));
        }
    }

    public function testAnotherIndexIsAnInvalidValue(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('No color of index 8');

        Color::fromIndex(8);
    }
}
