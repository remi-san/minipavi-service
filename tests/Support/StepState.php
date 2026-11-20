<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Controller\State;

/**
 * States of a form filled in steps, backed by numbers rather than text
 */
enum StepState: int implements State
{
    case First = 1;
    case Second = 2;
}
