<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

use MiniPaviService\Html\Html;
use MiniPaviService\Io\FileReader;
use MiniPaviService\Io\Files;
use MiniPaviService\Minitel\Color;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\ScreenSize;

/**
 * Draws a Screen in HTML, as the Minitel would show it (checked against MiniPavi's emulator):
 * row 0 (the status line) and the ROWS × COLS cells, in the Minitel's colours (text on the
 * background of its zone, Zone), with their sizes, inverse video (the text colour swapped with
 * that background), underlining (of the zones a delimiter starts, but not in inverse video, as
 * the emulator draws the key badges), masking (of a zone too: only the background shown) and
 * blinking (of the character only, on its background: shown for 0.75 s then hidden for 0.75 s,
 * the other way round in inverse video). Text is drawn with a Minitel font
 * (MINITEL_FONT: Minitel-Canvas's, whose glyphs fill the cells), embedded in style(). Mosaic
 * cells are drawn as their 2 × 3 pixels (separated when underlined), without depending on a
 * font, and so is 0x7F, a full block in text too, as SUB is shown. In text, 0x60, 0x7B, 0x7D
 * and 0x7E, which the Minitel font hasn't, are drawn as the emulator draws them: a line
 * across the middle of the cell, down its left or right edge, or across its top. Each cell
 * takes one column and one row, as in the emulator: a character bigger than a cell (double
 * width, height or size) is drawn at its size in each cell it covers, which shows its part of
 * it (Part), on that cell's background (e.g. a tall character's top half on the zone of the
 * row above), and a double-width one in the last column its left half only. No JavaScript.
 *
 * It draws the screen only: the page around it (e.g. Client\HtmlForm's) puts render() in its
 * body, and style() in its <style>. style() sets, for the whole page, --w and --h, the size
 * of a cell, --pad, the screen's padding, and --font, the Minitel font at the cell's size
 * (e.g. for the page's other controls); place() gives all the geometry of an element over the
 * screen (position, size, stacking), so the page's style only says how it looks.
 */
final readonly class HtmlRenderer
{
    // Minitel-Canvas's Minitel font (resources/fonts, CC0)
    public const MINITEL_FONT = __DIR__ . '/../../../resources/fonts/Minitel.ttf';

    // Pixels of a mosaic character, top left to bottom right, and the bit of each
    private const MOSAIC_BITS = [0x01, 0x02, 0x04, 0x08, 0x10, 0x40];

    // All six pixels in mosaic, a full block in text too
    private const FULL_BLOCK = "\x7F";

    // G0 characters the emulator draws as a line along an edge or across the middle of the
    // cell (its sprite sheet, font/ef9345-g0.png), which the Minitel font doesn't have: the
    // class of each line
    private const LINES = ["\x60" => 'middle', "\x7B" => 'left', "\x7D" => 'right', "\x7E" => 'top'];

    private const STYLE = <<<'CSS'
        :root { --w: 16px; --h: 20px; --pad: 12px; --font: var(--w)/var(--h) Minitel, "DejaVu Sans Mono", Menlo, Consolas, monospace; }
        .minitel { font: var(--font); position: relative; display: grid; grid-template-columns: repeat(40, var(--w)); grid-auto-rows: var(--h);
            padding: var(--pad); background: #000; }
        .status { border-bottom: 1px solid #444; }
        .c { position: relative; white-space: pre; text-align: center; overflow: hidden; }
        .g { position: absolute; left: 0; top: 0; width: var(--w); height: var(--h); transform-origin: top left; }
        .w2 > .g { transform: scaleX(2); }
        .h2 > .g { transform: scaleY(2); }
        .w2.h2 > .g { transform: scale(2); }
        .right > .g { left: calc(-1 * var(--w)); }
        .bottom > .g { top: calc(-1 * var(--h)); }
        .u, .u > .g { text-decoration: underline; }
        .blink, .blink-inverse { animation: blink 1.5s steps(1, end) infinite; }
        .blink-inverse { animation-delay: -0.75s; }
        @keyframes blink { 50% { color: transparent; } }
        .m { display: grid; grid-template: repeat(3, 1fr) / repeat(2, 1fr); height: 100%; }
        .m i { background: transparent; }
        .m i.on { background: currentColor; }
        .m.sep i.on { margin: 0 2px 2px 0; }
        .l { position: absolute; background: currentColor; }
        .l.top, .l.middle { left: 0; width: 100%; height: 10%; }
        .l.top { top: 0; }
        .l.middle { top: 40%; }
        .l.left, .l.right { top: 0; width: 12.5%; height: 100%; }
        .l.left { left: 0; }
        .l.right { right: 0; }
        CSS;

    /**
     * @param string $fontFile TrueType font of the text, embedded in style() (e.g.
     *                         MINITEL_FONT): its glyphs 1 em wide, 1.25 em high
     * @param FileReader $files reads the font file
     */
    public function __construct(
        private string $fontFile,
        private FileReader $files = new Files(),
    ) {}

    /**
     * The screen (a <div class="minitel">), with what overlay puts over it (e.g. input fields,
     * placed with place())
     *
     * @param string $overlay HTML over the screen
     */
    public function render(Screen $screen, string $overlay = ''): string
    {
        $cells = '';
        for ($row = 0; $row <= ScreenSize::ROWS; $row++) {
            for ($col = 1; $col <= ScreenSize::COLS; $col++) {
                $cells .= $this->cell($screen->cell($row, $col), $row);
            }
        }

        return '<div class="minitel">' . $cells . $overlay . '</div>';
    }

    /**
     * Style placing an element over the screen (e.g. an input field of the overlay) at a cell,
     * length cells wide and a cell high, its border included, and above the screen's cells:
     * all of the screen's geometry, kept with its CSS, so the element's own style needn't
     * know it
     *
     * @param Position $position the cell of the element's first character
     * @param int $length the element's width, in cells
     * @return string an inline style (style="..."): it takes over the element's own position,
     *                size, box-sizing and z-index
     */
    public function place(Position $position, int $length): string
    {
        return "position: absolute; left: calc(var(--pad) + {$position->col}*var(--w) - var(--w)); top: calc(var(--pad) + {$position->row}*var(--h)); width: calc({$length}*var(--w)); height: var(--h); box-sizing: border-box; z-index: 2;";
    }

    /**
     * The CSS of render()'s screen, its font included: read now, so only a page drawing the
     * screen needs it
     *
     * @throws \RuntimeException if the font file can't be read
     */
    public function style(): string
    {
        $font = $this->files->read($this->fontFile);
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
        $sized = $attributes->width() === 2 || $attributes->height() === 2;
        if ($attributes->width() === 2) {
            $classes[] = 'w2';
        }
        if ($attributes->height() === 2) {
            $classes[] = 'h2';
        }
        // The part of the character the cell shows (Part): its right or bottom half
        if ($cell->part->isRight()) {
            $classes[] = 'right';
        }
        if ($cell->part->isBottom()) {
            $classes[] = 'bottom';
        }
        if ($attributes->isBlinking()) {
            // Only the character blinks (its colour, which its underline and mosaic pixels
            // take, made transparent), on its background, as in MiniPavi's emulator; there,
            // in inverse video, it is hidden while the others are shown
            $classes[] = $inverse ? 'blink-inverse' : 'blink';
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

        $content = self::content($cell);
        if ($sized) {
            // Drawn at its size from the cell's top left corner, the cell showing its part
            $content = '<span class="g">' . $content . '</span>';
        }

        return '<span class="' . implode(' ', $classes) . '" style="' . $style . '">' . $content . '</span>';
    }

    /**
     * What the cell draws: pixels (mosaic, or a full block), a line (LINES), or text
     */
    private static function content(Cell $cell): string
    {
        $bytes = $cell->shownBytes();
        if (self::isPixels($bytes, $cell->attributes)) {
            return self::mosaic($bytes, $cell->attributes);
        }
        if (\array_key_exists($bytes, self::LINES)) {
            return self::line(self::LINES[$bytes]);
        }

        return Html::escape($cell->text());
    }

    /**
     * A mosaic character, drawn as its pixels: separated ones when they are (Attributes)
     */
    private static function mosaic(string $byte, Attributes $attributes): string
    {
        $classes = $attributes->isSeparated() ? 'm sep' : 'm';

        return '<span class="' . $classes . '">' . self::pixels($byte) . '</span>';
    }

    /**
     * A G0 character drawn as a line (LINES), in the text's colour, beside a space, which an
     * underlined zone draws its underline under
     */
    private static function line(string $class): string
    {
        return ' <i class="l ' . $class . '"></i>';
    }

    /**
     * Pixels of a mosaic character
     */
    private static function pixels(string $byte): string
    {
        $code = \ord($byte);
        $pixels = '';
        foreach (self::MOSAIC_BITS as $bit) {
            $pixels .= ($code & $bit) !== 0 ? '<i class="on"></i>' : '<i></i>';
        }

        return $pixels;
    }

    // Every character of the mosaic set is pixels (0x40 to 0x5F included, as MiniPavi's
    // emulator draws them), G2 ones (more than a byte) shown as text; 0x7F is a full block
    // in text too (the emulator's G0 character)
    private static function isPixels(string $bytes, Attributes $attributes): bool
    {
        return $bytes === self::FULL_BLOCK || $attributes->isMosaic() && \strlen($bytes) === 1;
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
