<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt\Screen;

use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\ScreenSize;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Page\Display\Key;
use MiniPaviService\Vdt\Screen\HtmlRenderer;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\TestCase;

final class HtmlRendererTest extends TestCase
{
    public function testDrawsEveryCellOfEveryRow(): void
    {
        $html = $this->render(new Vdt());

        self::assertStringStartsWith('<div class="minitel">', $html);
        self::assertSame((ScreenSize::ROWS + 1) * ScreenSize::COLS, substr_count($html, needle: '<span class="c'));
        // Row 0, the status line, is set apart
        self::assertSame(ScreenSize::COLS, substr_count($html, needle: 'class="c status"'));
        self::assertStringNotContainsString('<script', $html);
    }

    public function testItsStyleEmbedsTheMinitelFont(): void
    {
        $html = (new HtmlRenderer(HtmlRenderer::MINITEL_FONT))->style();

        self::assertStringContainsString(
            '@font-face { font-family: Minitel; src: url(data:font/ttf;base64,'
            . base64_encode((string) file_get_contents(HtmlRenderer::MINITEL_FONT))
            . ') format("truetype"); }',
            $html,
        );
        self::assertStringContainsString('--font: var(--w)/var(--h) Minitel,', $html);
        self::assertStringContainsString('.minitel { font: var(--font);', $html);
    }

    public function testMissingFontIsAnErrorOfItsStyleOnly(): void
    {
        $renderer = new HtmlRenderer('/missing.ttf');
        // The screen doesn't need it
        $renderer->render((new Vdt())->screen());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Font file not found: /missing.ttf');

        $renderer->style();
    }

    public function testPutsTheOverlayOverTheScreen(): void
    {
        self::assertStringEndsWith('<input class="field"></div>', (new HtmlRenderer(
            HtmlRenderer::MINITEL_FONT,
        ))->render((new Vdt())->screen(), overlay: '<input class="field">'));
    }

    public function testPlacesAnElementOverACellWithAllOfItsGeometry(): void
    {
        self::assertSame('position: absolute; left: calc(var(--pad) + 3*var(--w) - var(--w)); top: calc(var(--pad) + 2*var(--h));'
        . ' width: calc(4*var(--w)); height: var(--h); box-sizing: border-box; z-index: 2;', (new HtmlRenderer(
            HtmlRenderer::MINITEL_FONT,
        ))->place(new Position(row: 2, col: 3), length: 4));
    }

    public function testDrawsTextInItsColoursWithItsAccents(): void
    {
        $html = $this->render(Vdt::join(Vdt::position(new Position(row: 1)), new Vdt("\x1B\x41"), Vdt::text('é<')));

        self::assertStringContainsString('style="color:#ff0000;background:#000000">é</span>', $html);
        self::assertStringContainsString('>&lt;</span>', $html);
    }

    public function testInverseVideoSwapsTheColours(): void
    {
        $html = $this->renderCell("\x1B\x44\x1B\x57\x1B\x5Dx");

        self::assertStringContainsString('style="color:#ffffff;background:#0000ff">x</span>', $html);
    }

    public function testDoubleWidthTakesTwoColumns(): void
    {
        $html = $this->render(Vdt::join(Vdt::position(new Position(row: 1)), new Vdt("\x1B\x4E"), Vdt::text('W')));

        self::assertStringContainsString('class="c w2"', $html);
        // The character's second column is not drawn again
        self::assertSame(((ScreenSize::ROWS + 1) * ScreenSize::COLS) - 1, substr_count(
            $html,
            needle: '<span class="c',
        ));
    }

    public function testDoubleHeightBlinkAndUnderline(): void
    {
        // On row 2: row 1 has no double height
        self::assertStringContainsString('class="c h2"', $this->render(new Vdt("\x1F\x42\x41\x1B\x4Dx")));
        self::assertStringContainsString('class="c blink"', $this->renderCell("\x1B\x48x"));
        // Underlined from a delimiter, a space (Screen\Zone)
        self::assertStringContainsString('class="c u"', $this->renderCell("\x1B\x5A x"));
        // Not in inverse video: the key badges, as in MiniPavi's emulator
        self::assertStringNotContainsString('class="c u"', $this->renderCell("\x1B\x5A \x1B\x5Dx"));
        // Not the delimiter itself
        self::assertStringNotContainsString('class="c u"', $this->renderCell("\x1B\x5A "));
        // But the spaces in the zone, as MiniPavi's emulator draws them: x, a space, y
        self::assertSame(3, substr_count($this->renderCell("\x1B\x5A x y"), needle: 'class="c u"'));
    }

    public function testMosaicIsNeverDoubleHeight(): void
    {
        // ESC M then SO on row 2: the Minitel draws mosaic at normal size
        self::assertStringNotContainsString(' h2', $this->render(new Vdt("\x1F\x42\x41\x1B\x4D\x0E\x7F")));
    }

    public function testKeyBadgesArentUnderlined(): void
    {
        $html = $this->render((new Key('Envoi', new Position(1, 1)))->toVdt(new Terminal()));

        self::assertStringContainsString('>E</span>', $html);
        self::assertStringNotContainsString(' u"', $html);
    }

    public function testMaskedTextIsHidden(): void
    {
        $html = $this->renderCell("\x1B\x58s");

        self::assertStringNotContainsString('>s<', $html);
    }

    public function testDrawsMosaicPixels(): void
    {
        // 0x21: the top left pixel; 0x7F: all six
        self::assertStringContainsString(
            '<span class="m"><i class="on"></i><i></i><i></i><i></i><i></i><i></i></span>',
            $this->renderCell("\x0E\x21"),
        );
        self::assertSame(6, substr_count($this->renderCell("\x0E\x7F"), needle: '<i class="on">'));
        // 0x40 to 0x5F too: 0x4A is the right column
        self::assertStringContainsString(
            '<span class="m"><i></i><i class="on"></i><i></i><i class="on"></i><i></i><i class="on"></i></span>',
            $this->renderCell("\x0E\x4A"),
        );
        // Underlined mosaic is separated
        self::assertStringContainsString('<span class="m sep">', $this->renderCell("\x0E\x1B\x5A\x7F"));
    }

    public function testDrawsTheStatusLine(): void
    {
        $html = $this->render(Vdt::line0('Bonjour'));

        self::assertStringContainsString(
            '<span class="c status" style="color:#ffffff;background:#000000">B</span>',
            $html,
        );
    }

    private function render(Vdt $vdt): string
    {
        return (new HtmlRenderer(HtmlRenderer::MINITEL_FONT))->render($vdt->screen());
    }

    /**
     * Page of the screen Videotex content draws from row 1, column 1, e.g. attributes and a
     * character
     */
    private function renderCell(string $vdt): string
    {
        return $this->render(new Vdt("\x1F\x41\x41" . $vdt));
    }
}
