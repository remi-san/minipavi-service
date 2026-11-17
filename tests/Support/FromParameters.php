<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Controller\Parameters;
use MiniPaviService\Controller\Request;

/**
 * Parameters of a screen opened from another: the route it was opened from
 */
final readonly class FromParameters implements Parameters
{
    public function __construct(
        public string $from = '',
    ) {}

    public static function fromRequest(Request $request): static
    {
        return new self($request->string('from'));
    }

    public function toArray(): array
    {
        return ['from' => $this->from];
    }
}
