<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Writing;

use MiniPaviService\Minitel\Position;
use MiniPaviService\Vdt\Screen\Attributes;

/**
 * What OffsetWriter writes of a character the content draws: the position it is written at,
 * and the bytes and attributes it is written with. The position is the one the content draws
 * it at, not moved yet, for what of it shows once moved (OffsetWriter::asShown()), and the
 * moved one for where it is written (OffsetWriter::moved()): both are on the screen. Immutable.
 *
 * @internal
 */
final readonly class WrittenCharacter
{
    public function __construct(
        public Position $position,
        public string $bytes,
        public Attributes $attributes,
    ) {}
}
