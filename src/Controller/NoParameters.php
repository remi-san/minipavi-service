<?php

declare(strict_types=1);

namespace MiniPaviService\Controller;

/**
 * Parameters of a controller that keeps none
 */
final readonly class NoParameters implements Parameters
{
    public static function fromValues(Values $values): static
    {
        return new self();
    }

    public function toArray(): array
    {
        return [];
    }
}
