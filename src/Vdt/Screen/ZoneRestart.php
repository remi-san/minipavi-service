<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

/**
 * Where the zone the columns going off the screen leave starts again on a row moved left
 * (ShownScreen::restart()): the row and column (not moved) of the text character a delimiter
 * is written in, and the zone it starts. Immutable.
 *
 * @internal
 */
final readonly class ZoneRestart
{
    public function __construct(
        public int $row,
        public int $col,
        public Zone $zone,
    ) {}

    /**
     * Whether it is in the character drawn at row, col
     */
    public function isAt(int $row, int $col): bool
    {
        return $this->row === $row && $this->col === $col;
    }
}
