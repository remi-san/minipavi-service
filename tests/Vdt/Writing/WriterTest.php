<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt\Writing;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Screen;
use MiniPaviService\Vdt\Screen\Zone;
use MiniPaviService\Vdt\Vdt;
use MiniPaviService\Vdt\Writing\Writer;
use PHPUnit\Framework\TestCase;

final class WriterTest extends TestCase
{
    public function testWritesASpaceAsADelimiterOfAZoneTheCellsBeforeItArentIn(): void
    {
        $green = Zone::none()->withBackgroundWaiting(Color::Green)->delimited(Attributes::defaults());
        $writer = new Writer();
        $writer->glyph(row: 1, col: 1, bytes: ' ', width: 1, attributes: Attributes::defaults(), zone: $green);
        $writer->glyph(row: 1, col: 2, bytes: 'A', width: 1, attributes: Attributes::defaults(), zone: $green);

        // ESC R before the space: a green delimiter, A then in its zone
        $written = $writer->vdt();
        self::assertSame("\x1F\x41\x41\x1B\x52 A", $written);
        self::assertSame(Color::Green, Screen::of(new Vdt($written))->cell(1, 2)?->attributes->background());
    }
}
