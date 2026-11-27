<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Writing;

use MiniPaviService\Vdt\Screen\Attributes;

/**
 * What OffsetWriter writes of a character the content draws: the row and column it is written
 * at, and the bytes and attributes it is written with. Immutable.
 *
 * @internal
 */
final readonly class WrittenCharacter
{
    public function __construct(
        public int $row,
        public int $col,
        public string $bytes,
        public Attributes $attributes,
    ) {}
}
