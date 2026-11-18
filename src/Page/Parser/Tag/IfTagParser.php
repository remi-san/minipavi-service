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
    // A variable: a name, or a dotted path (Text\Placeholders)
    private const VARIABLE = '/^[A-Za-z_]\w*(?:\.\w+)*$/';

    public function parse(XmlTag $tag, TagPosition $position, NodesParser $nodes): ParsedNodes
    {
        [$variable, $negate] = $tag->check(static function () use ($tag, $position): array {
            if (!$position->template) {
                throw new PageXmlException('only in a page of type="template"');
            }
            $test = trim($tag->values()->string('test'));
            // One ! at most, then the variable, as in schema/page.xsd
            $negate = str_starts_with($test, '!');
            $variable = $negate ? substr($test, offset: 1) : $test;
            if ($variable === '') {
                throw new PageXmlException('test is missing');
            }
            if (preg_match(self::VARIABLE, $variable) !== 1) {
                throw new PageXmlException("test=\"{$test}\" must be a variable, or !variable");
            }

            return [$variable, $negate];
        });
        $children = $nodes->parse($tag->element, $position->nested());

        return ParsedNodes::node(new IfNode($variable, $negate, new Nodes($children->nodes), $tag->at));
    }
}
