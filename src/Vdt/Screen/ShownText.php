<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

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
        public int $row,
        public int $col,
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
     * Whether other is the same text character: drawn at the same row and column, a space or
     * not as this one is
     */
    public function isSameAs(?self $other): bool
    {
        return (
            $other !== null
            && $other->row === $this->row
            && $other->col === $this->col
            && $other->space === $this->space
        );
    }
}
