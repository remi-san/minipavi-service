<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser;

use MiniPaviService\Message\Messages;
use MiniPaviService\Page\Parser\Element\BackgroundBuilder;
use MiniPaviService\Page\Parser\Element\ElementBuilder;
use MiniPaviService\Page\Parser\Element\EndKeyBuilder;
use MiniPaviService\Page\Parser\Element\ImageBuilder;
use MiniPaviService\Page\Parser\Element\KeyBuilder;
use MiniPaviService\Page\Parser\Element\LineBuilder;
use MiniPaviService\Page\Parser\Element\TextBuilder;

/**
 * The display elements of the page XML format, by tag: the ElementBuilder of each. Used by
 * PageXmlParser, and by TemplatePage at display time (ElementNode), once placeholders are
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
     * The framework's elements (<background>, <image>, <text>, <line>, <key>, <endkey>), for a
     * page
     *
     * @param string $baseDir folder of the page, and of its <background> and <image> files
     * @param Messages $messages of the page, for the texts of <endkey>
     */
    public static function standard(string $baseDir, Messages $messages): self
    {
        $files = new PageFiles($baseDir);

        return new self(
            new BackgroundBuilder($files),
            new ImageBuilder($files),
            new TextBuilder(),
            new LineBuilder(),
            new KeyBuilder(),
            new EndKeyBuilder($messages),
        );
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
