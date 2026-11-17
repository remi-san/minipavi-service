<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Display;

use MiniPaviService\Core\Terminal;
use MiniPaviService\Vdt\Screen;
use MiniPaviService\Vdt\VdtHelper;

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
        public string $label,
        public string $shiftLabel,
        public ?int $row = null,
        public Color $color = Color::Green,
    ) {
        if ($row !== null) {
            Position::check($row, 1);
        }
    }

    // Adapters are websockets with a known Minitel; emulators don't know it
    public function getContent(Terminal $terminal): string
    {
        $badge = Key::badge($this->label, $this->color);
        // The badge is the label with two cells on each side
        $width = mb_strlen($this->label) + 4;

        if ($terminal->versionMinitel() != '???' && $terminal->typeSocket() == 'websocket') {
            $width += mb_strlen($this->shiftLabel);

            return VdtHelper::setPos(self::centered($width), $this->row ?? Screen::ROWS - 1) . Color::White->text() . VdtHelper::toG2($this->shiftLabel) . $badge;
        }

        return VdtHelper::setPos(self::centered($width), $this->row ?? Screen::ROWS) . $badge;
    }

    // Column of the first cell of something width wide, centered on the row
    private static function centered(int $width): int
    {
        return max(1, intdiv(Screen::COLS - $width, 2) + 1);
    }
}
