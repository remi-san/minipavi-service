<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Message\Message;

/**
 * Messages of a service, besides the framework's
 */
enum TestMessage: string implements Message
{
    case Welcome = 'welcome';
    case UnknownCode = 'code.unknown';
}
