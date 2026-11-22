<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

use MiniPaviService\Vdt\Player;
use MiniPaviService\Vdt\ScreenListener;
use MiniPaviService\Vdt\Vdt;

/**
 * The zones of the screen once content is drawn (Zones): what of() collects while playing it.
 *
 * It draws its zones as it listens (so it isn't readonly), for one content only: it is only
 * built, and played to, by of().
 *
 * @internal
 */
final class ScreenZones implements ScreenListener
{
    private readonly Zones $zones;

    private function __construct()
    {
        $this->zones = new Zones();
    }

    /**
     * The zones the content leaves on the screen
     */
    public static function of(Vdt $vdt): Zones
    {
        $zones = new self();
        (new Player())->play($vdt, $zones);

        return $zones->zones;
    }

    public function glyph(int $row, int $col, string $bytes, int $width, Attributes $attributes): void
    {
        $this->zones->draw($row, $col, $width, $attributes);
    }

    public function delimiter(int $row, int $col, int $width, Attributes $attributes, Zone $zone): void
    {
        $this->zones->delimiter($row, $col, $width, $attributes->height(), $zone);
    }

    public function clearLine(int $row, int $col, Attributes $attributes, ?Zone $delimiter): void
    {
        $this->zones->clearLine($row, $col, $attributes, $delimiter);
    }

    public function clear(): void
    {
        $this->zones->clear();
    }

    public function raw(string $bytes): void
    {
        // Changes no zone
    }
}
