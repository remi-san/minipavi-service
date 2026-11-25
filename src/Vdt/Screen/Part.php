<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

/**
 * The part of its character a cell shows, as MiniPavi's emulator keeps it in each cell
 * (decoder.js, printG0Char()): a character twice as wide (Attributes::width()) shows its
 * left half in its first column and its right half in the next one; a character twice as
 * tall (Attributes::height()) shows its top half on the row above and its bottom half on
 * the cursor's row. A character a cell high and wide is whole in its cell.
 */
final readonly class Part
{
    /**
     * @param bool $right whether the cell shows the right half of a double-width character
     * @param bool $bottom whether the cell shows the bottom half of a double-height character
     */
    public function __construct(
        private bool $right,
        private bool $bottom,
    ) {}

    /**
     * Whether the cell shows the right half of a double-width character
     */
    public function isRight(): bool
    {
        return $this->right;
    }

    /**
     * Whether the cell shows the bottom half of a double-height character
     */
    public function isBottom(): bool
    {
        return $this->bottom;
    }
}
