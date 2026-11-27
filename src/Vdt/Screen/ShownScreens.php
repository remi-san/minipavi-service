<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

use MiniPaviService\Minitel\Position;
use MiniPaviService\Vdt\Playing\Player;
use MiniPaviService\Vdt\Playing\ScreenListener;
use MiniPaviService\Vdt\Playing\StandInSpace;
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

    public function glyph(Position $position, string $bytes, Attributes $attributes): void
    {
        $this->screen->glyph($position->row, $position->col, $bytes, $attributes->width(), $attributes);
    }

    public function delimiter(Position $position, Attributes $attributes, Zone $zone): void
    {
        $this->screen->delimiter($position->row, $position->col, $attributes->width(), $attributes, $zone);
    }

    public function priorCharacter(Position $position, Attributes $attributes, ?Zone $delimiter): void
    {
        // As the space that stands in for it
        (new StandInSpace($position, $attributes, $delimiter))->tellTo($this);
    }

    public function clearLine(Position $position, Attributes $attributes, ?Zone $delimiter): void
    {
        $this->screen->clearLine($position->row, $position->col, $attributes, $delimiter);
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
