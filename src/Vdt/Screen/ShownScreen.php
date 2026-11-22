<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

use MiniPaviService\Minitel\ScreenSize;

/**
 * What a screen content draws shows once drawn, up to the clear screen that erases it or the
 * end of the content (ShownScreens): its zones (Zones), and which of its cells are text
 * spaces (a delimiter is one).
 *
 * It is drawn as ShownScreens listens (so it isn't readonly), then only read.
 *
 * @internal
 */
final class ShownScreen
{
    private Zones $zones;

    /**
     * @var array<int, array<int, bool>> whether each cell drawn is a text space, by row, then
     *                                   column (from 1)
     */
    private array $spaces = [];

    // The screen the clear screen erasing this one starts, once there is one
    private ?self $next = null;

    public function __construct()
    {
        $this->zones = new Zones();
    }

    /**
     * Whether the cell at row, col, drawn on this screen, is a text space: otherwise, what is
     * drawn there before is drawn over
     */
    public function endsAsASpace(int $row, int $col): bool
    {
        return $this->spaces[$row][$col];
    }

    /**
     * Where the zone the columns before first leave starts again on row, once moved left so
     * that first is its column 1, and that zone: at its first text space (that isn't a
     * delimiter) whose zone isn't the one the columns from first leave alone. Null if none
     * is, nor is any needed
     *
     * @return ?array{int, Zone} the column (not moved) and the zone
     */
    public function restart(int $row, int $first): ?array
    {
        $zone = $this->zones->before($row, $first);
        $alone = Zone::none();
        for ($col = $first; $col <= ScreenSize::COLS; $col++) {
            $zone = $this->zones->after($zone, $row, $col);
            $alone = $this->zones->after($alone, $row, $col);
            if (($this->spaces[$row][$col] ?? false) && !$zone->showsAs($alone)) {
                return [$col, $zone];
            }
        }

        return null;
    }

    /**
     * Draws a character at row, col, width cells wide, with these attributes (Zones::draw()):
     * a tall one on the row above too (Zones::cells())
     */
    public function glyph(int $row, int $col, string $bytes, int $width, Attributes $attributes): void
    {
        $this->zones->draw($row, $col, $width, $attributes);
        $space = $bytes === ' ' && !$attributes->isMosaic();
        foreach (Zones::cells($row, $col, $width, $attributes->height()) as [$cellRow, $cellCol]) {
            $this->spaces[$cellRow][$cellCol] = $space;
        }
    }

    /**
     * Draws a delimiter starting zone at row, col, width cells wide (Zones::delimiter())
     */
    public function delimiter(int $row, int $col, int $width, Attributes $attributes, Zone $zone): void
    {
        $this->glyph($row, $col, ' ', $width, $attributes);
        $this->zones->delimiter($row, $col, $width, $attributes->height(), $zone);
    }

    /**
     * CAN at row, col (Zones::clearLine()): spaces up to the last column
     */
    public function clearLine(int $row, int $col, Attributes $attributes, ?Zone $delimiter): void
    {
        $this->zones->clearLine($row, $col, $attributes, $delimiter);
        for ($cleared = $col; $cleared <= ScreenSize::COLS; $cleared++) {
            $this->spaces[$row][$cleared] = !$attributes->isMosaic();
        }
    }

    /**
     * The screen the clear screen erasing this one starts, the same each time: this one's row
     * 0, the status row, until more is drawn on it
     */
    public function cleared(): self
    {
        if ($this->next === null) {
            $this->next = new self();
            $this->next->zones = clone $this->zones;
            $this->next->zones->clear();
        }

        return $this->next;
    }
}
