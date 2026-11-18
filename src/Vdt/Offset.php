<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

/**
 * Moves Videotex content by x columns right and y rows down (negative: left, up). Videotex
 * positions are mostly absolute, but text also flows from one cell to the next and wraps at
 * the end of the row, so the content is played (Player) and written again (OffsetWriter) rather
 * than having its positions patched. Characters that end up off the screen are dropped
 * (instead of wrapping onto the next row), and the cursor is positioned again where needed,
 * with the current attributes (positioning resets them). Row 0, the status line, is not moved.
 */
final readonly class Offset
{
    public function __construct(
        private int $x,
        private int $y,
    ) {}

    public function apply(Vdt $vdt): Vdt
    {
        $out = new OffsetWriter($this->x, $this->y);
        (new Player())->play($vdt, $out);

        return $out->vdt();
    }
}
