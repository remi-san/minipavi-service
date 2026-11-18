<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser\Tag;

use MiniPaviService\Page\Parser\PageXmlException;
use MiniPaviService\Page\Template\IfNode;
use MiniPaviService\Page\Template\Nodes;

/**
 * <if test="variable"> or <if test="!variable">: its children drawn or not, in a page of
 * type="template"
 */
final readonly class IfTagParser implements TagParser
{
    public function parse(XmlTag $tag, TagPosition $position, NodesParser $nodes): ParsedNodes
    {
        $test = $tag->check(static function () use ($tag, $position): string {
            if (!$position->template) {
                throw new PageXmlException('only in a page of type="template"');
            }
            $test = $tag->values()->required('test');
            if ($test === '!') {
                throw new PageXmlException('test is missing');
            }

            return $test;
        });
        $children = $nodes->parse($tag->element, $position->nested());

        return ParsedNodes::node(
            new IfNode(
                ltrim($test, characters: '!'),
                str_starts_with($test, '!'),
                new Nodes($children->nodes),
                $tag->at,
            ),
        );
    }
}
