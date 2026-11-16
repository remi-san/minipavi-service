<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Display;

use MiniPaviService\Core\Terminal;
use MiniPaviService\Vdt\VdtHelper;
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
        public int $row,
        public int $col = 1,
        public Color $color = Color::Green,
        public ?Color $bg = null,
    ) {
        Position::check($row, $col);
    }

    public function getContent(Terminal $terminal): string
    {
        return VdtHelper::setPos($this->col, $this->row) . ($this->bg?->background() ?? '') . self::badge($this->label, $this->color);
    }

    /**
     * Badge of a key, at the cursor position (also used by EndKey)
     */
    public static function badge(string $label, Color $color): string
    {
        return Color::White->text() . Videotex::UNDERLINE_ON . ' ' . $color->text() . Videotex::INVERSE_ON . VdtHelper::toG2(' ' . $label . ' ') . Color::White->text() . Videotex::INVERSE_OFF . Videotex::UNDERLINE_OFF . ' ';
    }
}
