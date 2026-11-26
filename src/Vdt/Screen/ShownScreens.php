<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

use MiniPaviService\Vdt\Player;
use MiniPaviService\Vdt\ScreenListener;
use MiniPaviService\Vdt\Vdt;

/**
 * The screens content draws, as each shows once drawn (ShownScreen): one up to each clear
 * screen (FF), which starts the next one (ShownScreen::cleared()), then the last one, up to
 * the end of the content. What of() collects while playing it.
 *
 * It draws its screens as it listens (so it isn't readonly), for one content only: it is only
 * built, and played to, by of().
 *
 * @internal
 */
final class ShownScreens implements ScreenListener
{
    private readonly ShownScreen $first;

    // The screen being drawn
    private ShownScreen $screen;

    private function __construct()
    {
        $this->first = new ShownScreen();
        $this->screen = $this->first;
    }

    /**
     * The first screen the content draws, from which each clear screen leads to the next one
     * (ShownScreen::cleared())
     */
    public static function of(Vdt $vdt): ShownScreen
    {
        $screens = new self();
        (new Player())->play($vdt, $screens);

        return $screens->first;
    }

    public function glyph(int $row, int $col, string $bytes, int $width, Attributes $attributes): void
    {
        $this->screen->glyph($row, $col, $bytes, $width, $attributes);
    }

    public function delimiter(int $row, int $col, int $width, Attributes $attributes, Zone $zone): void
    {
        $this->screen->delimiter($row, $col, $width, $attributes, $zone);
    }

    public function priorCharacter(int $row, int $col, int $width, Attributes $attributes, ?Zone $delimiter): void
    {
        // As the space that stands in for it
        if ($delimiter !== null) {
            $this->screen->delimiter($row, $col, $width, $attributes, $delimiter);
            return;
        }
        $this->screen->glyph($row, $col, ' ', $width, $attributes);
    }

    public function clearLine(int $row, int $col, Attributes $attributes, ?Zone $delimiter): void
    {
        $this->screen->clearLine($row, $col, $attributes, $delimiter);
    }

    public function clear(): void
    {
        $this->screen = $this->screen->cleared();
    }

    public function raw(string $bytes): void
    {
        // Draws nothing
    }
}
