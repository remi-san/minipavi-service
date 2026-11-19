<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

use MiniPaviService\Html\Html;
use MiniPaviService\Io\Files;
use MiniPaviService\Minitel\Color;
use MiniPaviService\Minitel\ScreenSize;

/**
 * Draws a Screen in HTML, as the Minitel would show it (checked against MiniPavi's emulator):
 * row 0 (the status line) and the ROWS × COLS cells, in the Minitel's colours, with their
 * sizes, inverse video, underlining (of the zones a delimiter starts, Zone, but not in inverse
 * video, as the emulator draws the key badges) and blinking. Text is drawn with a Minitel font
 * (MINITEL_FONT: Minitel-Canvas's, whose glyphs fill the cells), embedded in style(). Mosaic
 * cells are drawn as their 2 × 3 pixels (separated when underlined), without depending on a
 * font. No JavaScript.
 *
 * It draws the screen only: the page around it (e.g. Client\HtmlForm's) puts render() in its
 * body, and style() in its <style>. style() sets, for the whole page, --w and --h, the size
 * of a cell, --pad, the screen's padding, and --font, the Minitel font at the cell's size
 * (e.g. for the page's other controls); place() places an element over the screen.
 */
final readonly class HtmlRenderer
{
    // Minitel-Canvas's Minitel font (resources/fonts, CC0)
    public const MINITEL_FONT = __DIR__ . '/../../../resources/fonts/Minitel.ttf';

    // Pixels of a mosaic character, top left to bottom right, and the bit of each
    private const MOSAIC_BITS = [0x01, 0x02, 0x04, 0x08, 0x10, 0x40];

    private const STYLE = <<<'CSS'
        :root { --w: 16px; --h: 20px; --pad: 12px; --font: var(--w)/var(--h) Minitel, "DejaVu Sans Mono", Menlo, Consolas, monospace; }
        .minitel { font: var(--font); position: relative; display: grid; grid-template-columns: repeat(40, var(--w)); grid-auto-rows: var(--h);
            padding: var(--pad); background: #000; }
        .status { border-bottom: 1px solid #444; }
        .c { position: relative; white-space: pre; text-align: center; overflow: visible; }
        .w2 { grid-column: span 2; transform: scaleX(2); transform-origin: left; width: var(--w); }
        .h2 { transform: scaleY(2); transform-origin: bottom; z-index: 1; }
        .w2.h2 { transform: scale(2); transform-origin: bottom left; }
        .u { text-decoration: underline; }
        .blink { animation: blink 1s steps(1, end) infinite; }
        @keyframes blink { 50% { visibility: hidden; } }
        .m { display: grid; grid-template: repeat(3, 1fr) / repeat(2, 1fr); height: 100%; }
        .m i { background: transparent; }
        .m i.on { background: currentColor; }
        .m.sep i.on { margin: 0 2px 2px 0; }
        CSS;

    /**
     * @param string $fontFile TrueType font of the text, embedded in style() (e.g.
     *                         MINITEL_FONT): its glyphs 1 em wide, 1.25 em high
     */
    public function __construct(
        private string $fontFile,
    ) {}

    /**
     * The screen (a <div class="minitel">), with what overlay puts over it (e.g. input fields,
     * positioned with --w, --h and --pad)
     *
     * @param string $overlay HTML over the screen
     */
    public function render(Screen $screen, string $overlay = ''): string
    {
        $cells = '';
        for ($row = 0; $row <= ScreenSize::ROWS; $row++) {
            for ($col = 1; $col <= ScreenSize::COLS; $col++) {
                $cell = $screen->cell($row, $col);
                $cells .= $this->cell($cell, $row);
                if ($cell !== null && $cell->attributes->width() === 2) {
                    $col++;
                }
            }
        }

        return '<div class="minitel">' . $cells . $overlay . '</div>';
    }

    /**
     * Style placing an element over the screen (e.g. an input field of the overlay) at a cell,
     * from column 1, row 0, length cells wide: the screen's geometry, kept with its CSS
     */
    public function place(int $col, int $row, int $length): string
    {
        return "left: calc(var(--pad) + {$col}*var(--w) - var(--w)); top: calc(var(--pad) + {$row}*var(--h)); width: calc({$length}*var(--w));";
    }

    /**
     * The CSS of render()'s screen, its font included: read now, so only a page drawing the
     * screen needs it
     *
     * @throws \RuntimeException if the font file can't be read
     */
    public function style(): string
    {
        $font = Files::read($this->fontFile);
        if ($font === null) {
            throw new \RuntimeException("Font file not found: {$this->fontFile}");
        }

        return (
            '@font-face { font-family: Minitel; src: url(data:font/ttf;base64,'
            . base64_encode($font)
            . ') format("truetype"); }'
            . self::STYLE
        );
    }

    private function cell(?Cell $cell, int $row): string
    {
        $classes = $row === 0 ? ['c', 'status'] : ['c'];
        if ($cell === null) {
            return '<span class="' . implode(' ', $classes) . '"> </span>';
        }

        $attributes = $cell->attributes;
        $foreground = self::color($attributes->foreground());
        $background = self::color($attributes->background());
        $mosaic = $attributes->isMosaic();
        $inverse = !$mosaic && $attributes->isInverse();
        if ($inverse) {
            [$foreground, $background] = [$background, $foreground];
        }
        if ($attributes->width() === 2) {
            $classes[] = 'w2';
        }
        if ($attributes->height() === 2) {
            $classes[] = 'h2';
        }
        if ($attributes->isBlinking()) {
            $classes[] = 'blink';
        }
        $underline = $attributes->isUnderlined();
        // Inverse video (the key badges, Page\Display\Key) isn't underlined, as in MiniPavi's
        // emulator; spaces in an underlined zone are (the delimiter starting it isn't: Zone)
        if (!$mosaic && $underline && !$inverse) {
            $classes[] = 'u';
        }

        $style = "color:{$foreground};background:{$background}";
        if ($attributes->isMasked()) {
            // Masked: hidden until revealed
            return '<span class="' . implode(' ', $classes) . '" style="' . $style . '"> </span>';
        }

        return (
            '<span class="'
            . implode(' ', $classes)
            . '" style="'
            . $style
            . '">'
            . (
                $mosaic && self::isMosaic($cell->bytes)
                    ? self::mosaic($cell->bytes, $underline)
                    : Html::escape($cell->text())
            )
            . '</span>'
        );
    }

    /**
     * Pixels of a mosaic character, separated when underlined
     */
    private static function mosaic(string $byte, bool $separated): string
    {
        $code = \ord($byte);
        $pixels = '';
        foreach (self::MOSAIC_BITS as $bit) {
            $pixels .= ($code & $bit) !== 0 ? '<i class="on"></i>' : '<i></i>';
        }

        return '<span class="m' . ($separated ? ' sep' : '') . '">' . $pixels . '</span>';
    }

    // Every character of the mosaic set is pixels (0x40 to 0x5F included, as MiniPavi's
    // emulator draws them); G2 ones (more than a byte) are shown as text
    private static function isMosaic(string $bytes): bool
    {
        return \strlen($bytes) === 1 && \ord($bytes) >= 0x20;
    }

    private static function color(Color $color): string
    {
        return match ($color) {
            Color::Black => '#000000',
            Color::Red => '#ff0000',
            Color::Green => '#00ff00',
            Color::Yellow => '#ffff00',
            Color::Blue => '#0000ff',
            Color::Magenta => '#ff00ff',
            Color::Cyan => '#00ffff',
            Color::White => '#ffffff',
        };
    }
}
