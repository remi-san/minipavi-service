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
 * Hint telling how to come back to this screen (<endkey> in the page XML format): the key
 * Connexion/fin, as a badge, centered. ESP32/Minimit adapters need Shift with the key: the
 * label of Shift comes before it, on row 23 for them, on row 24 otherwise. The labels are
 * messages (FrameworkMessage::EndKey, EndKeyShift).
 */
final readonly class EndKey implements Element
{
    /**
     * @param string $label label of the key
     * @param string $shiftLabel shown before it, for adapters
     */
    public function __construct(
        private string $label,
        private string $shiftLabel,
        private ?Position $position,
        private Color $color,
    ) {}

    public function toVdt(Terminal $terminal): Vdt
    {
        // The badge is the label with two cells on each side
        $width = mb_strlen($this->label) + 4;

        if ($terminal->isAdapter()) {
            $shiftWidth = mb_strlen($this->shiftLabel);
            $start = Position::centered($this->position->row ?? (ScreenSize::ROWS - 1), $width + $shiftWidth);

            return Vdt::join(
                Vdt::position($start),
                new Vdt(Videotex::textColor(Color::White)),
                Vdt::text($this->shiftLabel),
                Key::badge($this->label, $this->color, $start->col + $shiftWidth),
            );
        }

        $start = Position::centered($this->position->row ?? ScreenSize::ROWS, $width);

        return Vdt::position($start)->append(Key::badge($this->label, $this->color, $start->col));
    }
}
