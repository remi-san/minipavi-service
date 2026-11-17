<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt;

use MiniPaviService\Vdt\Attributes;
use MiniPaviService\Vdt\Cell;
use MiniPaviService\Vdt\HtmlRenderer;
use MiniPaviService\Vdt\Screen;
use MiniPaviService\Vdt\Vdt;
use MiniPaviService\Vdt\VdtHelper;
use PHPUnit\Framework\TestCase;

final class HtmlRendererTest extends TestCase
{
    public function testDrawsEveryCellOfEveryRowInAPage(): void
    {
        $html = (new HtmlRenderer())->render((new Vdt())->screen(), 'Service <test>');

        self::assertStringStartsWith('<!doctype html>', $html);
        self::assertStringContainsString('<title>Service &lt;test&gt;</title>', $html);
        self::assertSame((Screen::ROWS + 1) * Screen::COLS, substr_count($html, '<span class="c'));
        // Row 0, the status line, is set apart
        self::assertSame(Screen::COLS, substr_count($html, 'class="c status"'));
        self::assertStringNotContainsString('<script', $html);
    }

    public function testDrawsTextInItsColoursWithItsAccents(): void
    {
        $html = $this->render(Vdt::join(VdtHelper::setPos(1, 1), new Vdt("\x1B\x41"), Vdt::text('é<')));

        self::assertStringContainsString('style="color:#ff0000;background:#000000">é</span>', $html);
        self::assertStringContainsString('>&lt;</span>', $html);
    }

    public function testInverseVideoSwapsTheColours(): void
    {
        $html = $this->renderCell(new Cell('x', ['fg' => "\x44", 'bg' => "\x57", 'inverse' => "\x5D"] + Attributes::DEFAULTS));

        self::assertStringContainsString('style="color:#ffffff;background:#0000ff">x</span>', $html);
    }

    public function testDoubleWidthTakesTwoColumns(): void
    {
        $html = $this->render(Vdt::join(VdtHelper::setPos(1, 1), new Vdt("\x1B\x4E"), Vdt::text('W')));

        self::assertStringContainsString('class="c w2"', $html);
        // The character's second column is not drawn again
        self::assertSame((Screen::ROWS + 1) * Screen::COLS - 1, substr_count($html, '<span class="c'));
    }

    public function testDoubleHeightBlinkAndUnderline(): void
    {
        self::assertStringContainsString('class="c h2"', $this->renderCell(new Cell('x', ['size' => "\x4D"] + Attributes::DEFAULTS)));
        self::assertStringContainsString('class="c blink"', $this->renderCell(new Cell('x', ['blink' => "\x48"] + Attributes::DEFAULTS)));
        self::assertStringContainsString('class="c u"', $this->renderCell(new Cell('x', ['underline' => "\x5A"] + Attributes::DEFAULTS)));
    }

    public function testMaskedTextIsHidden(): void
    {
        $html = $this->renderCell(new Cell('s', ['mask' => "\x58"] + Attributes::DEFAULTS));

        self::assertStringNotContainsString('>s</span>', $html);
    }

    public function testDrawsMosaicPixels(): void
    {
        // 0x21: the top left pixel; 0x7F: all six
        $mosaic = ['charset' => Attributes::MOSAIC] + Attributes::DEFAULTS;

        self::assertStringContainsString('<span class="m"><i class="on"></i><i></i><i></i><i></i><i></i><i></i></span>', $this->renderCell(new Cell("\x21", $mosaic)));
        self::assertSame(6, substr_count($this->renderCell(new Cell("\x7F", $mosaic)), '<i class="on">'));
        // Underlined mosaic is separated
        self::assertStringContainsString('<span class="m sep">', $this->renderCell(new Cell("\x7F", ['underline' => "\x5A"] + $mosaic)));
    }

    public function testDrawsTheStatusLine(): void
    {
        $html = $this->render(VdtHelper::writeLine0('Bonjour'));

        self::assertStringContainsString('<span class="c status" style="color:#ffffff;background:#000000">B</span>', $html);
    }

    private function render(Vdt $vdt): string
    {
        return (new HtmlRenderer())->render($vdt->screen(), 'test');
    }

    /**
     * Page of a screen with this cell at row 1, column 1
     */
    private function renderCell(Cell $cell): string
    {
        $cells = array_fill(0, Screen::ROWS + 1, array_fill(1, Screen::COLS, null));
        $cells[1][1] = $cell;

        return (new HtmlRenderer())->render(new Screen($cells), 'test');
    }
}
