<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Display;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\ScreenSize;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Vdt\Vdt;
use MiniPaviService\Vdt\Videotex;

/**
 * Function key badge, e.g. " Envoi " in reverse video (<key> in the page XML format)
 */
final readonly class Key implements Element
{
    /**
     * @param ?Color $bg background around the badge, e.g. to draw it on a colored bar; it applies
     * from the badge's first character, a space
     */
    public function __construct(
        private string $label,
        private Position $position,
        private Color $color,
        private ?Color $bg,
    ) {}

    public function toVdt(Terminal $terminal): Vdt
    {
        return Vdt::join(
            Vdt::position($this->position),
            $this->bg === null ? new Vdt() : new Vdt(Videotex::backgroundColor($this->bg)),
            self::badge($this->label, $this->color, $this->position->col),
        );
    }

    /**
     * Badge of a key, at the cursor position, in column col (also used by EndKey): a
     * delimiter, the label in reverse video with a space on each side, then a space ending
     * the badge. What would go past the row's last column is left out, as the Minitel would
     * write it on the next row: the space after the label, and the one ending the badge,
     * whose zone ends with the row anyway
     */
    public static function badge(string $label, Color $color, int $col): Vdt
    {
        // Column of the label's last character, after the delimiter and the space before it
        $end = $col + 1 + mb_strlen($label);

        return Vdt::join(
            new Vdt(Videotex::textColor(Color::White)),
            new Vdt(Videotex::UNDERLINE_ON),
            Vdt::text(' '),
            new Vdt(Videotex::textColor($color)),
            new Vdt(Videotex::INVERSE_ON),
            Vdt::text(' ' . $label),
            $end < ScreenSize::COLS ? Vdt::text(' ') : new Vdt(),
            ($end + 1) < ScreenSize::COLS ? self::closing() : new Vdt(),
        );
    }

    /**
     * Space ending the badge: back to white, without reverse video nor underline
     */
    private static function closing(): Vdt
    {
        return Vdt::join(
            new Vdt(Videotex::textColor(Color::White)),
            new Vdt(Videotex::INVERSE_OFF),
            new Vdt(Videotex::UNDERLINE_OFF),
            Vdt::text(' '),
        );
    }
}
