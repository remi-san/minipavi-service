<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser\Tag;

use MiniPaviService\Page\Building\Placement;

/**
 * Where the elements a TagParser reads are: in a page of type="template" or not, inside a
 * <foreach> or not, at the top level of the page or not
 */
final readonly class TagPosition
{
    private function __construct(
        public bool $template,
        public bool $inLoop,
        public bool $topLevel,
    ) {}

    /**
     * The children of <page>
     *
     * @param bool $template whether it is of type="template"
     */
    public static function page(bool $template): self
    {
        return new self($template, inLoop: false, topLevel: true);
    }

    /**
     * The children of a <foreach> here
     */
    public function inLoop(): self
    {
        return new self($this->template, inLoop: true, topLevel: false);
    }

    /**
     * Where the elements here are, for their ElementBuilder, when the page is read
     */
    public function placement(): Placement
    {
        return $this->inLoop ? Placement::loop() : Placement::page();
    }

    /**
     * The children of an element here that isn't a loop (e.g. <if>)
     */
    public function nested(): self
    {
        return new self($this->template, $this->inLoop, topLevel: false);
    }
}
