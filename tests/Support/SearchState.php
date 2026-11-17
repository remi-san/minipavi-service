<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Controller\State;

/**
 * States of a search screen: the built-in ones it uses (same values as ControllerState), and
 * one of its own
 */
enum SearchState: string implements State
{
    case Init = 'init';
    case ReadInput = 'read-input';
    case ReadQuery = 'read-query';
}
