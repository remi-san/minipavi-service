<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Message\Message;

/**
 * Messages of a service whose names are, by mistake, those of other enums' messages
 */
enum SharingMessage: string implements Message
{
    case Oops = 'error';
    case Hello = 'welcome';
}
