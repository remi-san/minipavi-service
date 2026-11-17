<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt;

use MiniPaviService\Vdt\Vdt;
use MiniPaviService\Vdt\VdtHelper;
use PHPUnit\Framework\TestCase;

final class ScreenTest extends TestCase
{
    public function testPreviewShowsTextMosaicAndG2Characters(): void
    {
        // Row 1: text, then "e" with an accent (G2), then mosaic cells: with pixels, empty
        // on black, empty on blue. Row 2: cleared with a blue background from column 3.
        $vdt = "\x1F\x41\x41Hi\x19\x42e\x0E\x21\x20\x1B\x54\x20\x1F\x42\x43\x1B\x54\x18";

        $lines = explode("\n", Vdt::fromString($vdt)->screen()->preview());

        self::assertCount(26, $lines);
        self::assertSame(' 0|' . str_repeat(' ', 40) . '|', $lines[0]);
        self::assertSame(' 1|Hi?# #' . str_repeat(' ', 34) . '|', $lines[1]);
        self::assertSame(' 2|  ' . str_repeat(' ', 38) . '|', $lines[2]);
        self::assertSame('', $lines[25]);
    }

    public function testClearScreenEmptiesEveryRowButTheStatusRow(): void
    {
        $screen = Vdt::fromString("\x1F\x40\x41S\x1F\x45\x45X\x0C")->screen();

        self::assertSame('S', $screen->cell(0, 1)?->bytes);
        self::assertNull($screen->cell(5, 5));
    }

    public function testLineGivesTheTextOfARowWithItsAccents(): void
    {
        $screen = Vdt::join(VdtHelper::setPos(1, 3), Vdt::text('Dernière page, ça coûte 5£ ½ Œuvre'), VdtHelper::writeLine0('Accès refusé'))->screen();

        $text = 'Dernière page, ça coûte 5£ ½ Œuvre';
        self::assertSame($text . str_repeat(' ', 40 - mb_strlen($text)), $screen->line(3));
        self::assertStringStartsWith('Accès refusé', ltrim($screen->line(0)));
        self::assertSame(str_repeat(' ', 40), $screen->line(4));
        self::assertSame('', $screen->line(99));
    }

    public function testLineShowsMosaicAsInThePreview(): void
    {
        // A mosaic cell with pixels, one empty on black, one empty on blue
        $screen = Vdt::fromString("\x1F\x41\x41\x0E\x21\x20\x1B\x54\x20")->screen();

        self::assertSame('# #', substr($screen->line(1), 0, 3));
    }

    public function testTextGivesEveryRow(): void
    {
        $text = VdtHelper::setPos(1, 1)->append(Vdt::text('Hello'))->screen()->text();

        $lines = explode("\n", $text);
        self::assertCount(25, $lines);
        self::assertSame(str_pad('Hello', 40), $lines[1]);
    }
}
