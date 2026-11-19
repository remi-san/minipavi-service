<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

/**
 * Object whose magic properties fail with an \InvalidArgumentException of its own: a bug in a
 * service's code, not in the page or the message reading it
 */
final readonly class FailingProperties
{
    public function __isset(string $name): bool
    {
        return true;
    }

    public function __get(string $name): never
    {
        throw new \InvalidArgumentException("a bug reading {$name}");
    }
}
