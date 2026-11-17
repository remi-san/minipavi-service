<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Controller;

/**
 * States of a search screen: the built-in ones it uses, and one of its own
 */
enum SearchState: string
{
    case Init = Controller::STATE_INIT;
    case ReadInput = Controller::STATE_READ_INPUT;
    case ReadQuery = 'read-query';
}
