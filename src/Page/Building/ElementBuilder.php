<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Building;

use MiniPaviService\InvalidValueException;
use MiniPaviService\Page\Display\Element;

/**
 * Builds the display element of a tag of the page XML format (documented in the framework's
 * README.md, and in schema/page.xsd), from its attributes and text, and decides where it can
 * be and how it is placed (Placement): what ElementFactory has for each tag. A new element is
 * a builder, given by a PageElements.
 */
interface ElementBuilder
{
    /**
     * Tag of the element, e.g. text for <text>
     */
    public function tag(): string;

    /**
     * Checks, when the page is read, that the element can be where it is: e.g. <endkey>, once
     * per page, refuses a <foreach> (Placement::refuseInLoop())
     *
     * @throws PageXmlException if it can't, without location
     */
    public function checkPlacement(Placement $placement): void;

    /**
     * Element for these attributes and text (placeholders replaced in a template), placed
     * where placement says: e.g. by its row attribute (Placement::placeByRow()), relative to
     * the item inside a <foreach>
     *
     * @throws PageXmlException|InvalidValueException for invalid values, without location
     */
    public function build(AttributeValues $attributes, string $text, Placement $placement): Element;
}
