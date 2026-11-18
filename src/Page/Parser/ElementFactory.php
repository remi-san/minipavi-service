<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser;

use MiniPaviService\Message\Messages;
use MiniPaviService\Page\Parser\Element\ElementBuilder;

/**
 * The display elements of the page XML format, by tag: the ElementBuilder of each. Used by
 * PageXmlParser, and by TemplateContent at display time (ElementNode), once placeholders are
 * replaced.
 */
final readonly class ElementFactory
{
    /** @var array<string, ElementBuilder> */
    private array $builders;

    public function __construct(ElementBuilder ...$builders)
    {
        $byTag = [];
        foreach ($builders as $builder) {
            $byTag[$builder->tag()] = $builder;
        }
        $this->builders = $byTag;
    }

    /**
     * The elements of a page, from those of each set
     *
     * @param PageFiles $files the files of the page's folder
     * @param Messages $messages the page's messages
     */
    public static function of(PageFiles $files, Messages $messages, PageElements ...$elements): self
    {
        $builders = [];
        foreach ($elements as $set) {
            array_push($builders, ...$set->builders($files, $messages));
        }

        return new self(...$builders);
    }

    /**
     * Builder of the element of a tag, null if it isn't one
     */
    public function builder(string $tag): ?ElementBuilder
    {
        return $this->builders[$tag] ?? null;
    }

    /**
     * Tags of the elements
     *
     * @return list<string>
     */
    public function tags(): array
    {
        return array_keys($this->builders);
    }
}
