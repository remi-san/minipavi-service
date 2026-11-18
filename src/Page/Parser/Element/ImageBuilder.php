<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser\Element;

use MiniPaviService\Page\Display\Element;
use MiniPaviService\Page\Display\Image;
use MiniPaviService\Page\Parser\AttributeValues;
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

        return new Image(
            $this->pictures[$path] ??= Vdt::fromString($this->files->read($text)),
            row: $attributes->int('row', default: 1),
            col: $attributes->int('col', default: 1),
        );
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
