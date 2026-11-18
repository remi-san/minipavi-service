<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser\Tag;

use MiniPaviService\Page\Parser\ElementFactory;
use MiniPaviService\Page\Parser\PageXmlException;
use MiniPaviService\Xml\XmlElements;

/**
 * Reads the children of an element of a page XML file, each with the TagParser of its tag, or
 * as a display element (ElementTagParser) for any other tag
 */
final readonly class NodesParser
{
    private TagParser $elements;

    /**
     * @param array<string, TagParser> $parsers by tag
     * @param ElementFactory $elements the display elements, read for the other tags (and the
     *                                 unknown ones)
     * @param string $file name of the file, in error messages
     *
     * @throws \LogicException for a display element of the tag of a parser (e.g. <if>), which
     *                         the parser would read instead
     */
    public function __construct(
        private array $parsers,
        ElementFactory $elements,
        private string $file,
    ) {
        foreach ($elements->tags() as $tag) {
            if (!array_key_exists($tag, $parsers)) {
                continue;
            }
            throw new \LogicException(\sprintf(
                '%s: <%s> (%s) is a tag of the page format, not a display element',
                $file,
                $tag,
                get_debug_type($elements->builder($tag)),
            ));
        }
        $this->elements = new ElementTagParser($elements);
    }

    /**
     * The parsers of the page XML format, for a page whose display elements are factory's
     *
     * @throws \LogicException for a display element of the tag of an instruction (e.g. <if>)
     */
    public static function standard(ElementFactory $factory, string $file): self
    {
        return new self(
            [
                'message' => new MessageTagParser(),
                'input' => new InputTagParser(),
                'foreach' => new ForeachTagParser(),
                'if' => new IfTagParser(),
            ],
            $factory,
            $file,
        );
    }

    /**
     * The nodes of the children of parent, and the field of the <input> among them
     *
     * @throws PageXmlException giving the file and line
     */
    public function parse(\SimpleXMLElement $parent, TagPosition $position): ParsedNodes
    {
        $parsed = ParsedNodes::none();
        foreach (XmlElements::children($parent) as $name => $element) {
            $tag = XmlTag::of($name, $element, $this->file);
            $parsed = $parsed->with(($this->parsers[$name] ?? $this->elements)->parse(
                $tag,
                $position,
                $this,
            )) ?? throw $tag->error('only one <input> per page');
        }

        return $parsed;
    }
}
