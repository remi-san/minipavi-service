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
    /**
     * @param array<string, TagParser> $parsers by tag
     * @param TagParser $elements the display elements, and the unknown tags
     * @param string $file name of the file, in error messages
     */
    public function __construct(
        private array $parsers,
        private TagParser $elements,
        private string $file,
    ) {}

    /**
     * The parsers of the page XML format, for a page whose display elements are factory's
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
            new ElementTagParser($factory),
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
