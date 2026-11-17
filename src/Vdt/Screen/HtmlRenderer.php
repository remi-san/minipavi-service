<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

/**
 * Draws a Screen as an HTML page, as the Minitel would show it (checked against MiniPavi's
 * emulator): row 0 (the status line) and the ROWS × COLS cells, in the Minitel's colours,
 * with their sizes, inverse video, underlining (but not in inverse video, as the emulator
 * draws the key badges) and blinking. Text is drawn with a Minitel
 * font (MINITEL_FONT: Minitel-Canvas's, whose glyphs fill the cells), embedded in the page.
 * Mosaic cells are drawn as their 2 × 3 pixels (separated when underlined), without depending
 * on a font. Self-contained: inline CSS and font, no JavaScript.
 */
final readonly class HtmlRenderer
{
    // Minitel-Canvas's Minitel font (resources/fonts, CC0)
    public const MINITEL_FONT = __DIR__ . '/../../../resources/fonts/Minitel.ttf';

    // Colours of the Minitel, by their index in attribute codes (ESC 0x40 + index for the
    // character, 0x50 + index for the background)
    private const COLORS = ['#000000', '#ff0000', '#00ff00', '#ffff00', '#0000ff', '#ff00ff', '#00ffff', '#ffffff'];

    // Pixels of a mosaic character, top left to bottom right, and the bit of each
    private const MOSAIC_BITS = [0x01, 0x02, 0x04, 0x08, 0x10, 0x40];

    private const STYLE = <<<'CSS'
        :root { color-scheme: dark; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #1c1c1c; }
        .page { --w: 16px; --h: 20px; --pad: 12px; font: var(--w)/var(--h) Minitel, "DejaVu Sans Mono", Menlo, Consolas, monospace; }
        .minitel { position: relative; display: grid; grid-template-columns: repeat(40, var(--w)); grid-auto-rows: var(--h);
            padding: var(--pad); background: #000; }
        .field { position: absolute; height: var(--h); padding: 0; border: 0; border-bottom: 1px dotted #888; background: transparent;
            color: #fff; caret-color: #fff; font: inherit; letter-spacing: 0; outline: none; z-index: 2; }
        .field:focus { border-bottom-color: #fff; }
        .keys { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; margin-top: 10px; }
        .keys button { padding: 6px 0; border: 0; border-radius: 3px; background: #6b4b32; color: #fff; font: inherit; cursor: pointer; }
        .keys button:disabled { opacity: .35; cursor: default; }
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
     * @param string $fontFile TrueType font of the text, embedded in the page (e.g.
     *                         MINITEL_FONT): its glyphs 1 em wide, 1.25 em high
     */
    public function __construct(
        private string $fontFile,
    ) {}

    /**
     * The page showing the screen: a form posting back to its URL, with what overlay puts over
     * the screen (e.g. input fields, positioned with field()) and controls under it (e.g. the
     * function keys and hidden values)
     *
     * @param string $title of the page, in the browser (e.g. the URL of the service)
     * @param string $overlay HTML over the screen
     * @param string $controls HTML under the screen
     */
    public function render(Screen $screen, string $title, string $overlay = '', string $controls = ''): string
    {
        $cells = '';
        for ($row = 0; $row <= Screen::ROWS; $row++) {
            for ($col = 1; $col <= Screen::COLS; $col++) {
                $cell = $screen->cell($row, $col);
                $cells .= $this->cell($cell, $row);
                if ($cell !== null && Attributes::width($cell->attributes) === 2) {
                    $col++;
                }
            }
        }

        return (
            '<!doctype html><html><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>'
            . self::escape($title)
            . '</title><style>'
            . $this->fontFace()
            . self::STYLE
            . '</style></head>'
            . '<body><form method="post" class="page"><div class="minitel">'
            . $cells
            . $overlay
            . '</div>'
            . $controls
            . '</form></body></html>'
        );
    }

    /**
     * Style placing an element over the screen at a cell (from column 1, row 0), length cells
     * wide
     */
    public static function at(int $col, int $row, int $length): string
    {
        return "left: calc(var(--pad) + {$col}*var(--w) - var(--w)); top: calc(var(--pad) + {$row}*var(--h)); width: calc({$length}*var(--w));";
    }

    /**
     * Text escaped for HTML (content and attribute values)
     */
    public static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    private function cell(?Cell $cell, int $row): string
    {
        $classes = $row === 0 ? ['c', 'status'] : ['c'];
        if ($cell === null) {
            return '<span class="' . implode(' ', $classes) . '"> </span>';
        }

        $attributes = $cell->attributes;
        $foreground = self::color($attributes['fg'], 0x40);
        $background = self::color($attributes['bg'], 0x50);
        $mosaic = $attributes['charset'] === Attributes::MOSAIC;
        $inverse = !$mosaic && $attributes['inverse'] === "\x5D";
        if ($inverse) {
            [$foreground, $background] = [$background, $foreground];
        }
        if (Attributes::width($attributes) === 2) {
            $classes[] = 'w2';
        }
        if (\in_array($attributes['size'], ["\x4D", "\x4F"], true)) {
            $classes[] = 'h2';
        }
        if ($attributes['blink'] === "\x48") {
            $classes[] = 'blink';
        }
        $underline = $attributes['underline'] === "\x5A";
        // Neither spaces nor inverse video (the key badges, Page\Display\Key) are underlined,
        // as in MiniPavi's emulator
        if (!$mosaic && $underline && !$inverse && $cell->bytes !== ' ') {
            $classes[] = 'u';
        }

        $style = "color:{$foreground};background:{$background}";
        if ($attributes['mask'] === "\x58") {
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
                    : self::escape($cell->text())
            )
            . '</span>'
        );
    }

    /**
     * The font of the text, embedded
     *
     * @throws \RuntimeException if the font file can't be read
     */
    private function fontFace(): string
    {
        $font = @file_get_contents($this->fontFile);
        if ($font === false) {
            throw new \RuntimeException("Font file not found: {$this->fontFile}");
        }

        return (
            '@font-face { font-family: Minitel; src: url(data:font/ttf;base64,'
            . base64_encode($font)
            . ') format("truetype"); }'
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

    private static function color(string $code, int $base): string
    {
        return self::COLORS[\ord($code) - $base] ?? self::COLORS[0];
    }
}
