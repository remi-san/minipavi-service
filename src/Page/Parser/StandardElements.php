<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser;

use MiniPaviService\Message\Messages;
use MiniPaviService\Page\Parser\Element\BackgroundBuilder;
use MiniPaviService\Page\Parser\Element\EndKeyBuilder;
use MiniPaviService\Page\Parser\Element\ImageBuilder;
use MiniPaviService\Page\Parser\Element\KeyBuilder;
use MiniPaviService\Page\Parser\Element\LineBuilder;
use MiniPaviService\Page\Parser\Element\TextBuilder;

/**
 * The framework's display elements: <background>, <image>, <text>, <line>, <key>, <endkey>
 */
final readonly class StandardElements implements PageElements
{
    public function builders(PageFiles $files, Messages $messages): array
    {
        return [
            new BackgroundBuilder($files),
            new ImageBuilder($files),
            new TextBuilder(),
            new LineBuilder(),
            new KeyBuilder(),
            new EndKeyBuilder($messages),
        ];
    }
}
