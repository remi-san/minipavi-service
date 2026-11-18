<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Display;

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
        public Color $color = Color::Green,
        public ?Color $bg = null,
    ) {}

    public function getContent(Terminal $terminal): string
    {
        return (
            Vdt::position($this->position->col, $this->position->row)->toString()
            . ($this->bg?->background() ?? '')
            . self::badge($this->label, $this->color)
        );
    }

    /**
     * Badge of a key, at the cursor position (also used by EndKey)
     */
    public static function badge(string $label, Color $color): string
    {
        return (
            Color::White->text()
            . Videotex::UNDERLINE_ON
            . ' '
            . $color->text()
            . Videotex::INVERSE_ON
            . Vdt::text(' ' . $label . ' ')->toString()
            . Color::White->text()
            . Videotex::INVERSE_OFF
            . Videotex::UNDERLINE_OFF
            . ' '
        );
    }
}
