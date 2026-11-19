<?php

declare(strict_types=1);

namespace MiniPaviService\Minitel;

use MiniPaviService\InvalidValueException;

/**
 * Cells of a row of the Minitel screen, from start, length columns long, without going past
 * the last column (ScreenSize::COLS)
 */
final readonly class Span
{
    public int $length;

    /**
     * @param ?int $length defaults to the end of the row
     *
     * @throws InvalidValueException for a span going past the last column
     */
    public function __construct(
        public Position $start,
        ?int $length = null,
    ) {
        $this->length = $length ?? (ScreenSize::COLS + 1 - $start->col);
        if ($this->length < 1 || ($start->col + $this->length - 1) > ScreenSize::COLS) {
            throw new InvalidValueException(
                "Length {$this->length} from column {$start->col} goes past column " . ScreenSize::COLS,
            );
        }
    }
}
