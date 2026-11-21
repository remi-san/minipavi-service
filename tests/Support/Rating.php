<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

/**
 * An int-backed enum, as a service's element may read from an attribute
 */
enum Rating: int
{
    case One = 1;
    case Two = 2;
}
