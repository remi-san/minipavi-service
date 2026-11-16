<?php

namespace MiniPaviService\Vdt;

/**
 * Character shown in a cell of the Screen, with its attributes (see Attributes)
 */
final readonly class Cell {

    public function __construct(
        public string $bytes,
        public array $attributes,
    ) {
    }
}
