<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

/**
 * Object with properties a placeholder can't read from outside: only $name is public
 */
final readonly class PrivateProperties
{
    public function __construct(
        public string $name = 'Léa',
        protected string $nickname = 'Lé',
        private string $city = 'Lyon',
    ) {}

    /**
     * Uses the properties that aren't public, which would be unused otherwise
     */
    public function summary(): string
    {
        return "{$this->nickname} from {$this->city}";
    }
}
