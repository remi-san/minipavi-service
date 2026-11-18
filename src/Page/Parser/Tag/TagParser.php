<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser\Tag;

use MiniPaviService\Page\Parser\PageXmlException;

/**
 * Reads an element of a page XML file (format documented in the framework's README.md) where
 * it is: what NodesParser has for each tag (<input>, <foreach>, <if>, <message>), and for the
 * display elements (ElementTagParser)
 */
interface TagParser
{
    /**
     * @param NodesParser $nodes reads the element's children, if it has any
     *
     * @throws PageXmlException giving the file and line
     */
    public function parse(XmlTag $tag, TagPosition $position, NodesParser $nodes): ParsedNodes;
}
