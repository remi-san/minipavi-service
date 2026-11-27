<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

use MiniPaviService\Minitel\Position;

/**
 * The text character a cell shows once drawn (ShownScreen): where it is drawn, its bottom row
 * the cursor's (a tall one shows on the row above too, Zones::cells()), and whether it is a
 * space (a delimiter is one). Immutable.
 *
 * @internal
 */
final readonly class ShownText
{
    /**
     * @param bool $space whether it is a space
     */
    public function __construct(
        public Position $position,
        private bool $space,
    ) {}

    /**
     * Whether it is a space
     */
    public function isSpace(): bool
    {
        return $this->space;
    }

    /**
     * Whether other is the same text character: drawn at the same position, a space or not as
     * this one is
     */
    public function isSameAs(?self $other): bool
    {
        return (
            $other !== null
            && $other->position->row === $this->position->row
            && $other->position->col === $this->position->col
            && $other->space === $this->space
        );
    }
}
