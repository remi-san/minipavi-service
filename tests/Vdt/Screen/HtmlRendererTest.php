<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt\Screen;

use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Page\Display\Key;
use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Cell;
use MiniPaviService\Vdt\Screen\HtmlRenderer;
use MiniPaviService\Vdt\Screen\Screen;
use MiniPaviService\Vdt\Vdt;
use MiniPaviService\Vdt\VdtHelper;
use PHPUnit\Framework\TestCase;

final class HtmlRendererTest extends TestCase
{
    public function testDrawsEveryCellOfEveryRowInAPage(): void
    {
        $html = (new HtmlRenderer(HtmlRenderer::MINITEL_FONT))->render((new Vdt())->screen(), 'Service <test>');

        self::assertStringStartsWith('<!doctype html>', $html);
        self::assertStringContainsString('<title>Service &lt;test&gt;</title>', $html);
        self::assertSame((Screen::ROWS + 1) * Screen::COLS, substr_count($html, '<span class="c'));
        // Row 0, the status line, is set apart
        self::assertSame(Screen::COLS, substr_count($html, 'class="c status"'));
        self::assertStringNotContainsString('<script', $html);
    }

    public function testEmbedsTheMinitelFont(): void
    {
        $html = (new HtmlRenderer(HtmlRenderer::MINITEL_FONT))->render((new Vdt())->screen(), 'test');

        self::assertStringContainsString(
            '@font-face { font-family: Minitel; src: url(data:font/ttf;base64,'
            . base64_encode((string) file_get_contents(HtmlRenderer::MINITEL_FONT))
            . ') format("truetype"); }',
            $html,
        );
        self::assertStringContainsString('font: var(--w)/var(--h) Minitel,', $html);
    }

    public function testMissingFontIsAnError(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Font file not found: /missing.ttf');

        (new HtmlRenderer('/missing.ttf'))->render((new Vdt())->screen(), 'test');
    }

    public function testDrawsTextInItsColoursWithItsAccents(): void
    {
        $html = $this->render(Vdt::join(VdtHelper::setPos(1, 1), new Vdt("\x1B\x41"), Vdt::text('é<')));

        self::assertStringContainsString('style="color:#ff0000;background:#000000">é</span>', $html);
        self::assertStringContainsString('>&lt;</span>', $html);
    }

    public function testInverseVideoSwapsTheColours(): void
    {
        $html = $this->renderCell(new Cell('x', ['fg' => "\x44", 'bg' => "\x57", 'inverse' => "\x5D"]
        + Attributes::DEFAULTS));

        self::assertStringContainsString('style="color:#ffffff;background:#0000ff">x</span>', $html);
    }

    public function testDoubleWidthTakesTwoColumns(): void
    {
        $html = $this->render(Vdt::join(VdtHelper::setPos(1, 1), new Vdt("\x1B\x4E"), Vdt::text('W')));

        self::assertStringContainsString('class="c w2"', $html);
        // The character's second column is not drawn again
        self::assertSame(((Screen::ROWS + 1) * Screen::COLS) - 1, substr_count($html, '<span class="c'));
    }

    public function testDoubleHeightBlinkAndUnderline(): void
    {
        self::assertStringContainsString('class="c h2"', $this->renderCell(new Cell('x', ['size' => "\x4D"]
        + Attributes::DEFAULTS)));
        self::assertStringContainsString('class="c blink"', $this->renderCell(new Cell('x', ['blink' => "\x48"]
        + Attributes::DEFAULTS)));
        self::assertStringContainsString('class="c u"', $this->renderCell(new Cell('x', ['underline' => "\x5A"]
        + Attributes::DEFAULTS)));
        // Not in inverse video: the key badges, as in MiniPavi's emulator
        self::assertStringNotContainsString('class="c u"', $this->renderCell(new Cell('x', [
            'underline' => "\x5A",
            'inverse' => "\x5D",
        ] + Attributes::DEFAULTS)));
        // Not an underlined space, as in MiniPavi's emulator
        self::assertStringNotContainsString('class="c u"', $this->renderCell(new Cell(' ', ['underline' => "\x5A"]
        + Attributes::DEFAULTS)));
    }

    public function testKeyBadgesArentUnderlined(): void
    {
        $html = (new HtmlRenderer(HtmlRenderer::MINITEL_FONT))->render(
            (new Vdt((new Key('Envoi', 1, 1))->getContent(new Terminal())))->screen(),
            'test',
        );

        self::assertStringContainsString('>E</span>', $html);
        self::assertStringNotContainsString(' u"', $html);
    }

    public function testMaskedTextIsHidden(): void
    {
        $html = $this->renderCell(new Cell('s', ['mask' => "\x58"] + Attributes::DEFAULTS));

        self::assertStringNotContainsString('>s<', $html);
    }

    public function testDrawsMosaicPixels(): void
    {
        // 0x21: the top left pixel; 0x7F: all six
        $mosaic = ['charset' => Attributes::MOSAIC] + Attributes::DEFAULTS;

        self::assertStringContainsString(
            '<span class="m"><i class="on"></i><i></i><i></i><i></i><i></i><i></i></span>',
            $this->renderCell(new Cell("\x21", $mosaic)),
        );
        self::assertSame(6, substr_count($this->renderCell(new Cell("\x7F", $mosaic)), '<i class="on">'));
        // 0x40 to 0x5F too: 0x4A is the right column
        self::assertStringContainsString(
            '<span class="m"><i></i><i class="on"></i><i></i><i class="on"></i><i></i><i class="on"></i></span>',
            $this->renderCell(new Cell("\x4A", $mosaic)),
        );
        // Underlined mosaic is separated
        self::assertStringContainsString('<span class="m sep">', $this->renderCell(new Cell("\x7F", [
            'underline' => "\x5A",
        ] + $mosaic)));
    }

    public function testDrawsTheStatusLine(): void
    {
        $html = $this->render(VdtHelper::writeLine0('Bonjour'));

        self::assertStringContainsString(
            '<span class="c status" style="color:#ffffff;background:#000000">B</span>',
            $html,
        );
    }

    private function render(Vdt $vdt): string
    {
        return (new HtmlRenderer(HtmlRenderer::MINITEL_FONT))->render($vdt->screen(), 'test');
    }

    /**
     * Page of a screen with this cell at row 1, column 1
     */
    private function renderCell(Cell $cell): string
    {
        $cells = array_fill(0, Screen::ROWS + 1, array_fill(1, Screen::COLS, null));
        $cells[1][1] = $cell;

        return (new HtmlRenderer(HtmlRenderer::MINITEL_FONT))->render(new Screen($cells), 'test');
    }
}
