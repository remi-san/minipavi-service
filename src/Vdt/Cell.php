<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

/**
 * Character shown in a cell of the Screen, with its attributes (see Attributes)
 */
final readonly class Cell
{
    /**
     * @param array<string, string> $attributes see Attributes
     */
    public function __construct(
        public string $bytes,
        public array $attributes,
    ) {}
}
