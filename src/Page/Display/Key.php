<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Display;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Minitel\Position;
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
        public string $label,
        public Position $position,
        public Color $color,
        public ?Color $bg,
    ) {}

    public function toVdt(Terminal $terminal): Vdt
    {
        return Vdt::join(
            Vdt::position($this->position),
            $this->bg === null ? new Vdt() : new Vdt(Videotex::backgroundColor($this->bg)),
            self::badge($this->label, $this->color),
        );
    }

    /**
     * Badge of a key, at the cursor position (also used by EndKey)
     */
    public static function badge(string $label, Color $color): Vdt
    {
        return Vdt::join(
            new Vdt(Videotex::textColor(Color::White)),
            new Vdt(Videotex::UNDERLINE_ON),
            Vdt::text(' '),
            new Vdt(Videotex::textColor($color)),
            new Vdt(Videotex::INVERSE_ON),
            Vdt::text(' ' . $label . ' '),
            new Vdt(Videotex::textColor(Color::White)),
            new Vdt(Videotex::INVERSE_OFF),
            new Vdt(Videotex::UNDERLINE_OFF),
            Vdt::text(' '),
        );
    }
}
