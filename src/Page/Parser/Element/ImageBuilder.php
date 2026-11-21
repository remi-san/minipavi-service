<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser\Element;

use MiniPaviService\Page\Building\AttributeValues;
use MiniPaviService\Page\Building\ElementBuilder;
use MiniPaviService\Page\Display\Element;
use MiniPaviService\Page\Display\Image;
use MiniPaviService\Page\Parser\PageFiles;
use MiniPaviService\Vdt\Vdt;

/**
 * <image row col>file.vdt</image>: a Videotex picture of a file of the page's folder, drawn
 * at row and col. Each file is read once: templates build their elements on each display.
 */
final class ImageBuilder implements ElementBuilder
{
    /** @var array<string, Vdt> pictures, by file */
    private array $pictures = [];

    public function __construct(
        private readonly PageFiles $files,
    ) {}

    public function tag(): string
    {
        return 'image';
    }

    public function build(AttributeValues $attributes, string $text): Element
    {
        $path = $this->files->path($text);

        return new Image($this->pictures[$path] ??= new Vdt($this->files->read($text)), $attributes->position());
    }

    public function isPlacedByRow(): bool
    {
        return true;
    }

    public function isAllowedInLoop(): bool
    {
        return true;
    }
}
