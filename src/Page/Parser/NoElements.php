<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser;

use MiniPaviService\Message\Messages;

/**
 * No display elements besides the framework's: what PageXmlParser has when the service gives
 * none
 */
final readonly class NoElements implements PageElements
{
    public function builders(PageFiles $files, Messages $messages): array
    {
        return [];
    }
}
