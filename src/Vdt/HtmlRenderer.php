<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

/**
 * Draws a Screen as an HTML page, as the Minitel would show it: row 0 (the status line) and
 * the ROWS × COLS cells, in the Minitel's colours, with their sizes, inverse video,
 * underlining and blinking. Mosaic cells are drawn as their 2 × 3 pixels (separated when
 * underlined, as on the Minitel), without depending on a font. Self-contained: inline CSS,
 * no JavaScript.
 */
final class HtmlRenderer
{
    // Colours of the Minitel, by their index in attribute codes (ESC 0x40 + index for the
    // character, 0x50 + index for the background)
    private const COLORS = ['#000000', '#ff0000', '#00ff00', '#ffff00', '#0000ff', '#ff00ff', '#00ffff', '#ffffff'];

    // Pixels of a mosaic character, top left to bottom right, and the bit of each
    private const MOSAIC_BITS = [0x01, 0x02, 0x04, 0x08, 0x10, 0x40];

    private const STYLE = <<<'CSS'
        :root { color-scheme: dark; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #1c1c1c; }
        .minitel { --w: 16px; --h: 20px; display: grid; grid-template-columns: repeat(40, var(--w)); grid-auto-rows: var(--h);
            padding: 12px; background: #000; font: 18px/var(--h) "DejaVu Sans Mono", Menlo, Consolas, monospace; }
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
     * The page showing the screen
     *
     * @param string $title of the page, in the browser (e.g. the URL of the service)
     */
    public function render(Screen $screen, string $title): string
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

        return '<!doctype html><html><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . self::escape($title) . '</title><style>' . self::STYLE . '</style></head>'
            . '<body><div class="minitel">' . $cells . '</div></body></html>';
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
        if (!$mosaic && $attributes['inverse'] === "\x5D") {
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
        if (!$mosaic && $underline) {
            $classes[] = 'u';
        }

        $style = "color:$foreground;background:$background";
        if ($attributes['mask'] === "\x58") {
            // Masked: hidden until revealed
            return '<span class="' . implode(' ', $classes) . '" style="' . $style . '"> </span>';
        }

        return '<span class="' . implode(' ', $classes) . '" style="' . $style . '">'
            . ($mosaic && self::isMosaic($cell->bytes) ? self::mosaic($cell->bytes, $underline) : self::escape($cell->text()))
            . '</span>';
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

    // Mosaic characters are 0x20 to 0x3F and 0x60 to 0x7F; the others are shown as text
    private static function isMosaic(string $bytes): bool
    {
        return \strlen($bytes) === 1 && (\ord($bytes) & 0x20) !== 0;
    }

    private static function color(string $code, int $base): string
    {
        return self::COLORS[\ord($code) - $base] ?? self::COLORS[0];
    }

    private static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
