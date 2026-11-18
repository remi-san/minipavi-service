<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser\Element;

use MiniPaviService\Page\Display\Background;
use MiniPaviService\Page\Display\Element;
use MiniPaviService\Page\Parser\AttributeValues;
use MiniPaviService\Page\Parser\PageFiles;

/**
 * <background>file.vdt</background>: Videotex content sent as is, from a file of the page's folder
 */
final readonly class BackgroundBuilder implements ElementBuilder
{
    public function __construct(
        private PageFiles $files,
    ) {}

    public function tag(): string
    {
        return 'background';
    }

    public function build(AttributeValues $attributes, string $text): Element
    {
        return new Background($this->files->read($text));
    }

    // Its content positions itself
    public function isPlacedByRow(): bool
    {
        return false;
    }

    public function isAllowedInLoop(): bool
    {
        return true;
    }
}
