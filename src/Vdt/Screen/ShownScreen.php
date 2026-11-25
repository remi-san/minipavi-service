<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

use MiniPaviService\Minitel\ScreenSize;

/**
 * What a screen content draws shows once drawn, up to the clear screen that erases it or the
 * end of the content (ShownScreens): its zones (Zones), and which of its cells show a text
 * space (a delimiter is one), and drawn where: a tall one is on the row above too
 * (Zones::cells()), which a tall character drawn from the row below may cover, or not.
 *
 * It is drawn as ShownScreens listens (so it isn't readonly), then only read.
 *
 * @internal
 */
final class ShownScreen
{
    private Zones $zones;

    /**
     * @var array<int, array<int, ?array{int, int}>> by row, then column (from 1), the row and
     *                                               column a text space a cell shows is drawn
     *                                               at; null if it shows another character
     */
    private array $spaces = [];

    // The screen the clear screen erasing this one starts, once there is one
    private ?self $next = null;

    public function __construct()
    {
        $this->zones = new Zones();
    }

    /**
     * Whether a text space drawn at row, col on this screen, width cells wide and height cells
     * high, shows in one of its cells (Zones::cells()) once drawn: otherwise, it is drawn over
     */
    public function shows(int $row, int $col, int $width, int $height): bool
    {
        foreach (Zones::cells($row, $col, $width, $height) as [$cellRow, $cellCol]) {
            if (($this->spaces[$cellRow][$cellCol][0] ?? null) === $row) {
                return true;
            }
        }

        return false;
    }

    /**
     * Where the zone the columns before first leave starts again on row, once moved left so
     * that first is its column 1, and that zone: at its first text space (that isn't a
     * delimiter) whose zone isn't the one the columns from first leave alone, drawn on row,
     * or on the row below when it is the top half of a tall space; the row and column it is
     * drawn at (the column before first for a double-width one whose right half only is from
     * first). Null if none is, nor is any needed
     *
     * @return ?array{int, int, Zone} the row and column (not moved), and the zone
     */
    public function restart(int $row, int $first): ?array
    {
        $zone = $this->zones->before($row, $first);
        $alone = Zone::none();
        for ($col = $first; $col <= ScreenSize::COLS; $col++) {
            $zone = $this->zones->after($zone, $row, $col);
            $alone = $this->zones->after($alone, $row, $col);
            $space = $this->spaces[$row][$col] ?? null;
            if ($space !== null && !$zone->showsAs($alone)) {
                return [$space[0], $space[1], $zone];
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
        $this->cover($row, $col, $bytes, $width, $attributes);
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
     * CAN at row, col (Zones::clearLine()): spaces with these attributes up to the last
     * column, on the row above too in double height
     */
    public function clearLine(int $row, int $col, Attributes $attributes, ?Zone $delimiter): void
    {
        $this->zones->clearLine($row, $col, $attributes, $delimiter);
        for ($cleared = $col; $cleared <= ScreenSize::COLS; $cleared++) {
            $this->cover($row, $cleared, ' ', width: $attributes->width(), attributes: $attributes);
        }
    }

    /**
     * Remembers, in each cell a character drawn at row, col covers (Zones::cells()), whether it
     * shows a text space, and drawn where
     */
    private function cover(int $row, int $col, string $bytes, int $width, Attributes $attributes): void
    {
        $space = $bytes === ' ' && !$attributes->isMosaic() ? [$row, $col] : null;
        foreach (Zones::cells($row, $col, $width, $attributes->height()) as [$cellRow, $cellCol]) {
            $this->spaces[$cellRow][$cellCol] = $space;
        }
    }

    /**
     * Whether the top half of the tall text space drawn at row, col, on the row above, still
     * shows, in zone: a tall delimiter starting zone there shows what it does
     */
    public function showsOnTop(int $row, int $col, Zone $zone): bool
    {
        $above = $row - 1;

        return (
            ($this->spaces[$above][$col] ?? null) === $this->spaces[$row][$col]
            && $this->zones->before($above, $col)->showsAs($zone)
        );
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
