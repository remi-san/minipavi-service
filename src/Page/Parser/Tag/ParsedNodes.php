<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser\Tag;

use MiniPaviService\Minitel\InputField;
use MiniPaviService\Page\Template\Node;

/**
 * What elements of a page XML file are read as: the nodes that draw the page, and the field of
 * its <input> if one is among them
 */
final readonly class ParsedNodes
{
    /**
     * @param list<Node> $nodes
     */
    private function __construct(
        public array $nodes = [],
        public ?InputField $input = null,
    ) {}

    public static function none(): self
    {
        return new self();
    }

    public static function node(Node $node): self
    {
        return new self([$node]);
    }

    public static function input(InputField $input): self
    {
        return new self(input: $input);
    }

    /**
     * These, then others; null if both have an <input>, which a page has once at most
     */
    public function with(self $others): ?self
    {
        if ($this->input !== null && $others->input !== null) {
            return null;
        }

        return new self([...$this->nodes, ...$others->nodes], $this->input ?? $others->input);
    }
}
