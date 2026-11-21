<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser\Element;

use MiniPaviService\Page\Building\AttributeValues;
use MiniPaviService\Page\Building\ElementBuilder;
use MiniPaviService\Page\Building\Placement;
use MiniPaviService\Page\Display\Background;
use MiniPaviService\Page\Display\Element;
use MiniPaviService\Page\Parser\PageFiles;
use MiniPaviService\Vdt\Vdt;

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

    public function checkPlacement(Placement $placement): void {}

    public function build(AttributeValues $attributes, string $text, Placement $placement): Element
    {
        // Its content positions itself, wherever it is
        return new Background(new Vdt($this->files->read($text)));
    }
}
