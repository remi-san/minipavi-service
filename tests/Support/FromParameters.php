<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Controller\Parameters\Parameters;
use MiniPaviService\Controller\Parameters\Values;

/**
 * Parameters of a screen opened from another: the route it was opened from
 */
final readonly class FromParameters implements Parameters
{
    public function __construct(
        public string $from = '',
    ) {}

    public static function fromValues(Values $values): static
    {
        return new self($values->string('from'));
    }

    public function toArray(): array
    {
        return ['from' => $this->from];
    }
}
