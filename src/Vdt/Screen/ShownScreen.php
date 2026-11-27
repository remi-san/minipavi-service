<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\ScreenSize;

/**
 * What a screen content draws shows once drawn, up to the clear screen that erases it or the
 * end of the content (ShownScreens): its zones (Zones), and which of its cells show a text
 * character, a space (a delimiter is one) or not, and drawn where: a tall one is on the row
 * above too (Zones::cells()), which a tall character drawn from the row below may cover, or
 * not.
 *
 * It is drawn as ShownScreens listens (so it isn't readonly), then only read.
 *
 * @internal
 */
final class ShownScreen
{
    private Zones $zones;

    /**
     * @var array<int, array<int, ?ShownText>> by row, then column (from 1), the text character
     *                                         a cell shows; null if it shows a mosaic one
     */
    private array $text = [];

    // The screen the clear screen erasing this one starts, once there is one
    private ?self $next = null;

    public function __construct()
    {
        $this->zones = new Zones();
    }

    /**
     * Whether a text space drawn at position on this screen with these attributes shows in one
     * of its cells (Zones::cells()) once drawn: otherwise, it is drawn over
     */
    public function shows(Position $position, Attributes $attributes): bool
    {
        $row = $position->row;
        foreach (Zones::cells($row, $position->col, $attributes->width(), $attributes->height()) as [
            $cellRow,
            $cellCol,
        ]) {
            $text = $this->text[$cellRow][$cellCol] ?? null;
            if ($text !== null && $text->isSpace() && $text->position->row === $row) {
                return true;
            }
        }

        return false;
    }

    /**
     * Where the zone the columns before first leave starts again on row, once moved left so
     * that first is its column 1, and that zone: at its first text space (that isn't a
     * delimiter), the right half in first of a double-width text character, which is
     * written as a space there (#170), or the top half of a tall text character drawn from
     * below last, the last row on the screen once moved, which is written as a space too
     * (#173), whose zone isn't the one the columns from first leave alone, drawn on row, or on
     * the row below when it is the top half of a tall one; the row and column it is drawn at
     * (the column before first for a double-width one whose right half only is from first).
     * Null if none is, nor is any needed
     */
    public function restart(int $row, int $first, int $last): ?ZoneRestart
    {
        $zone = $this->zones->before($row, $first);
        $alone = Zone::none();
        for ($col = $first; $col <= ScreenSize::COLS; $col++) {
            $zone = $this->zones->after($zone, $row, $col);
            $alone = $this->zones->after($alone, $row, $col);
            $text = $this->text[$row][$col] ?? null;
            if (
                $text !== null
                && ($text->isSpace() || $text->position->col < $first || $text->position->row > $last)
                && !$zone->showsAs($alone)
            ) {
                return new ZoneRestart($text->position, $zone);
            }
        }

        return null;
    }

    /**
     * Draws a character at position, with these attributes (Zones::draw()): a tall one on the
     * row above too (Zones::cells())
     */
    public function glyph(Position $position, string $bytes, Attributes $attributes): void
    {
        $this->zones->draw($position, $attributes);
        $this->cover($position, $bytes, $attributes);
    }

    /**
     * Draws a delimiter starting zone at position (Zones::delimiter())
     */
    public function delimiter(Position $position, Attributes $attributes, Zone $zone): void
    {
        $this->glyph($position, ' ', $attributes);
        $this->zones->delimiter($position, $attributes, $zone);
    }

    /**
     * CAN at position (Zones::clearLine()): spaces with these attributes up to the last
     * column, on the row above too in double height
     */
    public function clearLine(Position $position, Attributes $attributes, ?Zone $delimiter): void
    {
        $this->zones->clearLine($position, $attributes, $delimiter);
        for ($cleared = $position->col; $cleared <= ScreenSize::COLS; $cleared++) {
            $this->cover(new Position($position->row, $cleared), ' ', $attributes);
        }
    }

    /**
     * Remembers, in each cell a character drawn at position covers (Zones::cells()), whether
     * it shows a text character, drawn where, and whether it is a space
     */
    private function cover(Position $position, string $bytes, Attributes $attributes): void
    {
        $text = $attributes->isMosaic() ? null : new ShownText($position, space: $bytes === ' ');
        $cells = Zones::cells($position->row, $position->col, $attributes->width(), $attributes->height());
        foreach ($cells as [$cellRow, $cellCol]) {
            $this->text[$cellRow][$cellCol] = $text;
        }
    }

    /**
     * Whether the top half of the tall text character drawn at position (a space, or one whose
     * right half only stays, written as a space), on the row above, still shows, in zone: a
     * tall delimiter starting zone there shows what it does
     */
    public function showsOnTop(Position $position, Zone $zone): bool
    {
        $row = $position->row;
        $col = $position->col;
        $above = $row - 1;

        return (
            ($this->text[$above][$col] ?? null)?->isSameAs($this->text[$row][$col]) === true
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
