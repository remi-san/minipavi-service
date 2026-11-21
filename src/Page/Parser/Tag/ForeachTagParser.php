<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser\Tag;

use MiniPaviService\Page\Parser\PageXmlException;
use MiniPaviService\Page\Template\Nodes;

/**
 * <foreach items as row step>: its children drawn once per item, in a page of type="template".
 * Its row is absolute outside of any <foreach>, relative to the enclosing item inside.
 */
final readonly class ForeachTagParser implements TagParser
{
    public function parse(XmlTag $tag, TagPosition $position, NodesParser $nodes): ParsedNodes
    {
        $loop = $tag->check(static function () use ($tag, $position): ForeachLoop {
            if (!$position->template) {
                throw new PageXmlException('only in a page of type="template"');
            }
            $values = $tag->values();
            $items = $values->required('items');
            $as = $values->required('as');
            if (!preg_match('/^[A-Za-z_]\w*$/', $as) || $as === 'loop') {
                throw new PageXmlException("as=\"{$as}\" must be a variable name, other than loop");
            }

            return new ForeachLoop(
                $items,
                $as,
                $values->int('row', default: $position->inLoop ? 0 : 1),
                $values->int('step', default: 1),
            );
        });
        $children = $nodes->parse($tag->element, $position->inLoop());

        return ParsedNodes::node($loop->node(new Nodes($children->nodes), $tag->at));
    }
}
