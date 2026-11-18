<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser\Element;

use MiniPaviService\Page\Display\Element;
use MiniPaviService\Page\Parser\AttributeValues;
use MiniPaviService\Page\Parser\PageXmlException;

/**
 * Builds the display element of a tag of the page XML format (documented in the framework's
 * README.md, and in schema/page.xsd), from its attributes and text: what ElementFactory has
 * for each tag. A new element is a builder, given to ElementFactory::standard().
 */
interface ElementBuilder
{
    /**
     * Tag of the element, e.g. text for <text>
     */
    public function tag(): string;

    /**
     * Element for these attributes and text (placeholders replaced in a template)
     *
     * @throws PageXmlException|\InvalidArgumentException for invalid values, without location
     */
    public function build(AttributeValues $attributes, string $text): Element;

    /**
     * Whether the element is placed by its row attribute, which inside a <foreach> is
     * relative to the current item
     */
    public function isPlacedByRow(): bool;

    /**
     * Whether the element can be inside a <foreach>, drawn once per item
     */
    public function isAllowedInLoop(): bool;
}
