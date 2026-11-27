<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt\Screen;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\ScreenSize;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Page\Display\Key;
use MiniPaviService\Tests\Support\MemoryFiles;
use MiniPaviService\Vdt\Screen\HtmlRenderer;
use MiniPaviService\Vdt\Screen\Screen;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testReadsTheFontThroughItsFileReader(): void
    {
        $html = (new HtmlRenderer('/fonts/font.ttf', new MemoryFiles(['/fonts/font.ttf' => 'FONT'])))->style();

        self::assertStringContainsString('src: url(data:font/ttf;base64,' . base64_encode('FONT') . ')', $html);
    }

    public function testMissingFontIsAnErrorOfItsStyleOnly(): void
    {
        $renderer = new HtmlRenderer('/missing.ttf');
        // The screen doesn't need it
        $renderer->render(Screen::of(new Vdt()));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Font file not found: /missing.ttf');

        $renderer->style();
    }

    public function testPutsTheOverlayOverTheScreen(): void
    {
        self::assertStringEndsWith('<input class="field"></div>', (new HtmlRenderer(
            HtmlRenderer::MINITEL_FONT,
        ))->render(Screen::of(new Vdt()), overlay: '<input class="field">'));
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

    public function testDrawsAG2CharacterTheMinitelDoesntHaveAsItDoes(): void
    {
        // A diaeresis and u, which the Minitel shows as _ (#90)
        $html = $this->render(Vdt::join(Vdt::position(new Position(row: 1)), new Vdt("\x19\x48u")));

        self::assertStringContainsString('style="color:#ffffff;background:#000000">_</span>', $html);
    }

    public function testInverseVideoSwapsTheColours(): void
    {
        // Blue text, in a zone on white (from its delimiter, a space): the text white on blue
        $html = $this->renderCell("\x1B\x44\x1B\x57 \x1B\x5Dx");

        self::assertStringContainsString('style="color:#ffffff;background:#0000ff">x</span>', $html);
        // With no delimiter, the zone is black: the text black on blue
        self::assertStringContainsString(
            'style="color:#000000;background:#0000ff">x</span>',
            $this->renderCell("\x1B\x44\x1B\x57\x1B\x5Dx"),
        );
    }

    public function testDoubleWidthShowsAHalfInEachOfItsColumns(): void
    {
        $html = $this->render(Vdt::join(Vdt::position(new Position(row: 1)), new Vdt("\x1B\x4E"), Vdt::text('W')));

        // Each cell in its own column, as MiniPavi's emulator draws them: the character
        // twice as wide, each column showing its half
        self::assertSame((ScreenSize::ROWS + 1) * ScreenSize::COLS, substr_count($html, needle: '<span class="c'));
        self::assertStringContainsString(
            '<span class="c w2" style="color:#ffffff;background:#000000"><span class="g">W</span></span>'
            . '<span class="c w2 right" style="color:#ffffff;background:#000000"><span class="g">W</span></span>',
            $html,
        );
    }

    public function testDoubleWidthInTheLastColumnShowsItsLeftHalfOnly(): void
    {
        // As in MiniPavi's emulator (#141): the row keeps its 40 columns, the next row is
        // where it was
        $html = $this->render(new Vdt("\x1F\x41\x68\x1B\x4EW\x1F\x42\x41Z"));

        self::assertSame((ScreenSize::ROWS + 1) * ScreenSize::COLS, substr_count($html, needle: '<span class="c'));
        self::assertStringContainsString(
            '<span class="c w2" style="color:#ffffff;background:#000000"><span class="g">W</span></span>'
            . '<span class="c" style="color:#ffffff;background:#000000">Z</span>',
            $html,
        );
        self::assertStringNotContainsString('right', $html);
    }

    public function testDoubleHeightShowsItsTopHalfOnTheRowAbove(): void
    {
        // Row 1: a zone on blue; ESC M and x on row 2, column 3: its top half on blue, on
        // row 1, its bottom half on black, as in MiniPavi's emulator (#141)
        $html = $this->render(new Vdt("\x1F\x41\x41\x1B\x54 \x18\x1F\x42\x43\x1B\x4Dx"));

        self::assertStringContainsString(
            '<span class="c h2" style="color:#ffffff;background:#0000ff"><span class="g">x</span></span>',
            $html,
        );
        self::assertStringContainsString(
            '<span class="c h2 bottom" style="color:#ffffff;background:#000000"><span class="g">x</span></span>',
            $html,
        );
    }

    public function testEachPartShowsTheCharacterScaledFromItsCell(): void
    {
        $style = (new HtmlRenderer(HtmlRenderer::MINITEL_FONT))->style();

        self::assertStringContainsString(
            '.c { position: relative; white-space: pre; text-align: center; overflow: hidden; }',
            $style,
        );
        self::assertStringContainsString('.w2 > .g { transform: scaleX(2); }', $style);
        self::assertStringContainsString('.h2 > .g { transform: scaleY(2); }', $style);
        self::assertStringContainsString('.w2.h2 > .g { transform: scale(2); }', $style);
        self::assertStringContainsString('.right > .g { left: calc(-1 * var(--w)); }', $style);
        self::assertStringContainsString('.bottom > .g { top: calc(-1 * var(--h)); }', $style);
    }

    public function testDoubleHeightBlinkAndUnderline(): void
    {
        // On row 2: row 1 has no double height
        self::assertStringContainsString('class="c h2 bottom"', $this->render(new Vdt("\x1F\x42\x41\x1B\x4Dx")));
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

    public function testBlinkingHidesTheCharacterOnly(): void
    {
        // As MiniPavi's emulator does, every 1.5 s: the character (its pixels in mosaic, its
        // underline) shown for 0.75 s, then hidden for 0.75 s, its background kept
        $style = (new HtmlRenderer(HtmlRenderer::MINITEL_FONT))->style();

        self::assertStringContainsString(
            '.blink, .blink-inverse { animation: blink 1.5s steps(1, end) infinite; }',
            $style,
        );
        self::assertStringContainsString('@keyframes blink { 50% { color: transparent; } }', $style);
        self::assertStringNotContainsString('visibility', $style);
        // In a zone on blue (from its delimiter, a space): the cell keeps the zone's background
        self::assertStringContainsString(
            '<span class="c blink" style="color:#ffffff;background:#0000ff">x</span>',
            $this->renderCell("\x1B\x54 \x1B\x48x"),
        );
        // In double size too
        self::assertStringContainsString(
            '<span class="c w2 h2 bottom blink" style="color:#ffffff;background:#000000"><span class="g">x</span></span>',
            $this->render(new Vdt("\x1F\x42\x41\x1B\x4F\x1B\x48x")),
        );
        // And in mosaic, where inverse video doesn't apply
        self::assertStringContainsString(
            '<span class="c blink" style="color:#ffffff;background:#0000ff"><span class="m">',
            $this->renderCell("\x1B\x48\x1B\x5D\x0E\x1B\x54\x7F"),
        );
    }

    public function testBlinkingInInverseVideoShowsTheCharacterWhenTheOthersAreHidden(): void
    {
        // As MiniPavi's emulator does: hidden for the first 0.75 s, on the text's colour
        $style = (new HtmlRenderer(HtmlRenderer::MINITEL_FONT))->style();

        self::assertStringContainsString('.blink-inverse { animation-delay: -0.75s; }', $style);
        self::assertStringContainsString(
            '<span class="c blink-inverse" style="color:#000000;background:#ffffff">x</span>',
            $this->renderCell("\x1B\x48\x1B\x5Dx"),
        );
    }

    public function testMosaicIsNeverDoubleHeight(): void
    {
        // ESC M then SO on row 2: the Minitel draws mosaic at normal size
        self::assertStringNotContainsString(' h2', $this->render(new Vdt("\x1F\x42\x41\x1B\x4D\x0E\x7F")));
    }

    public function testKeyBadgesArentUnderlined(): void
    {
        $html = $this->render((new Key('Envoi', new Position(1, 1), Color::Green, bg: null))->toVdt(new Terminal()));

        self::assertStringContainsString('>E</span>', $html);
        self::assertStringNotContainsString(' u"', $html);
    }

    public function testMaskedZoneIsHidden(): void
    {
        // Masking starts at a delimiter (Screen\Zone): s, blinking, then a mosaic character,
        // both masked, only the zone's background shown
        $html = $this->renderCell("\x1B\x58\x1B\x54 \x1B\x48s\x0E\x7F");

        $masked = '<span class="c blink" style="color:#ffffff;background:#0000ff"> </span>';
        self::assertSame(
            self::screenWith('<span class="c" style="color:#ffffff;background:#0000ff"> </span>', $masked, $masked),
            $html,
        );
        // Not before one
        self::assertSame(
            self::screenWith('<span class="c" style="color:#ffffff;background:#000000">s</span>'),
            $this->renderCell("\x1B\x58s"),
        );
    }

    public function testDrawsTextOnTheBackgroundOfItsZone(): void
    {
        // From a delimiter, a space
        self::assertStringContainsString(
            'style="color:#ffffff;background:#0000ff">A</span>',
            $this->renderCell("\x1B\x54 A"),
        );
        // Not before one
        self::assertStringContainsString(
            'style="color:#ffffff;background:#000000">A</span>',
            $this->renderCell("\x1B\x54A"),
        );
    }

    public function testDrawsMosaicPixels(): void
    {
        // 0x21: the top left pixel; 0x7F: all six
        self::assertSame(
            self::screenWith(
                '<span class="c" style="color:#ffffff;background:#000000"><span class="m">'
                . '<i class="on"></i><i></i><i></i><i></i><i></i><i></i></span></span>',
                '<span class="c" style="color:#ffffff;background:#000000"><span class="m">'
                . str_repeat('<i class="on"></i>', times: 6)
                . '</span></span>',
            ),
            $this->renderCell("\x0E\x21\x7F"),
        );
        // 0x40 to 0x5F too: 0x4A is the right column
        self::assertStringContainsString(
            '<span class="m"><i></i><i class="on"></i><i></i><i class="on"></i><i></i><i class="on"></i></span>',
            $this->renderCell("\x0E\x4A"),
        );
        // Underlined mosaic is separated
        self::assertStringContainsString('<span class="m sep">', $this->renderCell("\x0E\x1B\x5A\x7F"));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function fullBlocks(): iterable
    {
        // MiniPavi's emulator prints SUB as 0x7F, in mosaic all six pixels, and in text the
        // G0 character 0x7F, a full block too
        yield 'SUB in mosaic' => ["\x0E\x1A"];
        yield 'SUB in text' => ["\x1A"];
        yield '0x7F in text' => ["\x7F"];
    }

    #[DataProvider('fullBlocks')]
    public function testDrawsAFullBlockAsTheEmulatorDoes(string $vdt): void
    {
        self::assertSame(
            self::screenWith(
                '<span class="c" style="color:#ffffff;background:#000000"><span class="m">'
                . str_repeat('<i class="on"></i>', times: 6)
                . '</span></span>',
            ),
            $this->renderCell($vdt),
        );
    }

    /**
     * The G0 characters MiniPavi's emulator draws as a line across or down the cell (its
     * sprite sheet, font/ef9345-g0.png), which the Minitel font doesn't have (#143)
     *
     * @return iterable<string, array{string, string}> the character, and the edge of its line
     */
    public static function g0Lines(): iterable
    {
        yield '0x60, a line across the middle' => ["\x60", 'middle'];
        yield '0x7B, a line down the left edge' => ["\x7B", 'left'];
        yield '0x7D, a line down the right edge' => ["\x7D", 'right'];
        yield '0x7E, a line across the top' => ["\x7E", 'top'];
    }

    #[DataProvider('g0Lines')]
    public function testDrawsTheG0LinesAsTheEmulatorDoes(string $char, string $line): void
    {
        self::assertSame(
            self::screenWith(
                '<span class="c" style="color:#ffffff;background:#000000"> <i class="l ' . $line . '"></i></span>',
            ),
            $this->renderCell($char),
        );
    }

    #[DataProvider('g0Lines')]
    public function testDrawsTheG0LinesAtTheirSize(string $char, string $line): void
    {
        // Double size on row 2: the bottom left quarter, scaled with the character
        self::assertStringContainsString(
            '<span class="c w2 h2 bottom" style="color:#ffffff;background:#000000">'
            . '<span class="g"> <i class="l '
            . $line
            . '"></i></span></span>',
            $this->render(new Vdt("\x1F\x42\x41\x1B\x4F{$char}")),
        );
    }

    public function testG0LinesAreUnderlinedAndBlinkAsText(): void
    {
        // Drawn in the text's colour, which blinking makes transparent, beside a space,
        // which underlining draws under
        $style = (new HtmlRenderer(HtmlRenderer::MINITEL_FONT))->style();
        self::assertStringContainsString('.l { position: absolute; background: currentColor; }', $style);
        self::assertStringContainsString(
            '<span class="c blink u" style="color:#ffffff;background:#000000"> <i class="l top"></i></span>',
            // From a delimiter, a space, which starts the underlined zone
            $this->renderCell("\x1B\x5A \x1B\x48\x7E"),
        );
    }

    public function testOtherG0CharactersAreDrawnWithTheMinitelFont(): void
    {
        // As the emulator draws them: 0x5E an arrow up (Cell::text()), which the font has,
        // 0x5F a line across the bottom, 0x7C a line down the middle
        $cell = static fn(string $text) => (
            '<span class="c" style="color:#ffffff;background:#000000">' . $text . '</span>'
        );
        self::assertSame(self::screenWith($cell('↑'), $cell('_'), $cell('|')), $this->renderCell("\x5E\x5F\x7C"));
    }

    public function testMosaicCharactersOfTheG0LinesArePixels(): void
    {
        self::assertStringNotContainsString('class="l', $this->renderCell("\x0E\x60\x7B\x7D\x7E"));
    }

    public function testMosaicSpaceIsDrawnAsPixelsOnItsBackground(): void
    {
        // No pixel, on the blue background: not as text (Cell::text() shows it as #)
        self::assertSame(
            self::screenWith(
                '<span class="c" style="color:#ffffff;background:#0000ff"><span class="m">'
                . str_repeat('<i></i>', times: 6)
                . '</span></span>',
            ),
            $this->renderCell("\x1B\x54\x0E "),
        );
    }

    public function testDrawsEachColour(): void
    {
        // A in each foreground colour, ESC 0x40 (black) to ESC 0x47 (white)
        $html = $this->renderCell(implode('', array_map(
            static fn(int $code) => "\x1B" . \chr($code) . 'A',
            range(start: 0x40, end: 0x47),
        )));

        self::assertSame(
            self::screenWith(...array_map(
                static fn(string $color) => '<span class="c" style="color:' . $color . ';background:#000000">A</span>',
                ['#000000', '#ff0000', '#00ff00', '#ffff00', '#0000ff', '#ff00ff', '#00ffff', '#ffffff'],
            )),
            $html,
        );
    }

    public function testDrawsTheStatusLine(): void
    {
        $html = $this->render(Vdt::line0('Bonjour'));

        self::assertStringContainsString(
            '<span class="c status" style="color:#ffffff;background:#000000">B</span>',
            $html,
        );
    }

    /**
     * HTML of a screen whose row 1 starts with these cells, the others empty
     */
    private static function screenWith(string ...$cells): string
    {
        return (
            '<div class="minitel">'
            . str_repeat('<span class="c status"> </span>', ScreenSize::COLS)
            . implode('', array_pad(
                $cells,
                length: ScreenSize::ROWS * ScreenSize::COLS,
                value: '<span class="c"> </span>',
            ))
            . '</div>'
        );
    }

    private function render(Vdt $vdt): string
    {
        return (new HtmlRenderer(HtmlRenderer::MINITEL_FONT))->render(Screen::of($vdt));
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
