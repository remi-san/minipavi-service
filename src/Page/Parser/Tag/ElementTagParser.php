<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser\Tag;

use MiniPaviService\Page\Parser\Element\ElementBuilder;
use MiniPaviService\Page\Parser\ElementFactory;
use MiniPaviService\Page\Parser\PageXmlException;
use MiniPaviService\Page\Template\ElementNode;

/**
 * A display element (<text>, <line>, <image>...), built by its ElementBuilder: {{placeholders}}
 * only in a page of type="template", whose elements without any are built now too, so that
 * their errors (e.g. a missing file) are found when the page is read, as in a static page.
 * Any other tag is an unknown element.
 */
final readonly class ElementTagParser implements TagParser
{
    public function __construct(
        private ElementFactory $factory,
    ) {}

    public function parse(XmlTag $tag, TagPosition $position, NodesParser $nodes): ParsedNodes
    {
        $builder = $tag->check(function () use ($tag, $position): ElementBuilder {
            $builder = $this->factory->builder($tag->name) ?? throw new PageXmlException('unknown element');
            if ($position->inLoop && !$builder->isAllowedInLoop()) {
                throw new PageXmlException('not inside <foreach>');
            }
            $hasPlaceholder = $tag->hasPlaceholder();
            if ($hasPlaceholder && !$position->template) {
                throw new PageXmlException('{{placeholders}} only in a page of type="template"');
            }
            if ($position->template && !$hasPlaceholder) {
                // Built now only to find its errors while parsing (the template builds it again
                // when displayed). In a <foreach>, its row is relative to the item: checked then
                $values = $tag->values();
                $builder->build($position->inLoop ? $values->without('row') : $values, $tag->text);
            }

            return $builder;
        });

        return ParsedNodes::node(new ElementNode($builder, $tag->attributes, $tag->text, $tag->at));
    }
}
