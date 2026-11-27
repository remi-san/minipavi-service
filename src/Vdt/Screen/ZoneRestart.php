<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

use MiniPaviService\Minitel\Position;

/**
 * Where the zone the columns going off the screen leave starts again on a row moved left
 * (ShownScreen::restart()): the position (not moved) of the text character a delimiter is
 * written in, and the zone it starts. Immutable.
 *
 * @internal
 */
final readonly class ZoneRestart
{
    public function __construct(
        public Position $position,
        public Zone $zone,
    ) {}

    /**
     * Whether it is in the character drawn at position
     */
    public function isAt(Position $position): bool
    {
        return $this->position->row === $position->row && $this->position->col === $position->col;
    }
}
