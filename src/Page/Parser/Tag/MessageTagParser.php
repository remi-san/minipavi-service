<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser\Tag;

/**
 * <message name="...">: a message of the service the page redefines, only at its top level.
 * Its text is read with the page's messages (PageXmlParser), before the display: no node.
 */
final readonly class MessageTagParser implements TagParser
{
    public function parse(XmlTag $tag, TagPosition $position, NodesParser $nodes): ParsedNodes
    {
        if (!$position->topLevel) {
            throw $tag->error('only at the top level of the page');
        }

        return ParsedNodes::none();
    }
}
